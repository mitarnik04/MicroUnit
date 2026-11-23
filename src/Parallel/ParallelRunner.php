<?php

namespace MicroUnit\Parallel;

use MicroUnit\Assertion\AssertionFailure;
use MicroUnit\Assertion\NotProvided;
use MicroUnit\Core\RunSummary;
use MicroUnit\Core\Test;
use MicroUnit\Core\TestError;
use MicroUnit\Core\TestPlan;
use MicroUnit\Core\TestResult;
use MicroUnit\Core\TestStatus;
use MicroUnit\Coverage\CoverageData;
use MicroUnit\Helpers\Diff;
use MicroUnit\Helpers\DiffLine;
use MicroUnit\Helpers\DiffLineType;
use MicroUnit\Helpers\ExportedValue;

/**
 * Splits a test plan over worker processes (whole suites, balanced) and reports the results in
 * definition order, exactly as a sequential run would. Workers are MicroUnit itself, started with
 * --worker=i/n; only core PHP (proc_open) is used.
 */
final readonly class ParallelRunner
{
    private const int POLL_INTERVAL_IN_MICROSECONDS = 5_000;

    /** Classes a test result may contain; nothing else is ever unserialized. */
    private const array TRANSPORT_CLASSES = [
        TestResult::class, TestStatus::class, TestError::class, AssertionFailure::class,
        NotProvided::class, Diff::class, DiffLine::class, DiffLineType::class, ExportedValue::class,
    ];

    /**
     * @param \Closure(int $index, int $count, string $outputFile): list<string> $workerCommand
     * @param int<2, max> $workerCount a single worker runs sequentially, without this runner
     */
    public function __construct(
        private \Closure $workerCommand,
        private int $workerCount,
        private string $temporaryDirectory,
    ) {}

    /** @param \Closure(string $suite, list<TestResult> $results): void $onSuiteFinished */
    public function run(TestPlan $plan, \Closure $onSuiteFinished): ParallelOutcome
    {
        $workers = $this->startWorkers();
        $this->waitFor($workers);

        $resultsById = [];
        $coverage = null;
        foreach ($workers as $index => $worker) {
            $share = $plan->partition($index, $this->workerCount);
            $result = $this->readResult($worker['output'], $share);
            if (is_string($result)) {
                $result = $this->crashResult($share, $worker['log'], $worker['exitCode'], $result);
            }
            foreach ($result->results as $testResult) {
                $resultsById[$testResult->id] = $testResult;
            }
            if ($result->coverage !== null) {
                $coverage = $coverage === null ? $result->coverage : $coverage->merge($result->coverage);
            }
            @unlink($worker['output']);
            @unlink($worker['log']);
        }

        $summary = new RunSummary();
        foreach ($plan->bySuite() as [$tester, $tests]) {
            $results = [];
            foreach ($tests as $test) {
                if (isset($resultsById[$test->id])) {
                    $results[] = $resultsById[$test->id];
                }
            }
            if ($results !== []) {
                $summary->add($results);
                $onSuiteFinished($tester->suite, $results);
            }
        }

        return new ParallelOutcome($summary, $coverage);
    }

    /** @return list<array{process: resource, output: string, log: string, exitCode: ?int}> */
    private function startWorkers(): array
    {
        $runId = bin2hex(random_bytes(4));
        $workers = [];
        for ($index = 0; $index < $this->workerCount; $index++) {
            $output = $this->temporaryDirectory . DIRECTORY_SEPARATOR . "worker-$runId-$index.result";
            $log = $this->temporaryDirectory . DIRECTORY_SEPARATOR . "worker-$runId-$index.log";
            $process = proc_open(
                ($this->workerCommand)($index, $this->workerCount, $output),
                [0 => ['file', PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null', 'r'], 1 => ['file', $log, 'w'], 2 => ['file', $log, 'a']],
                $pipes,
                null,
                self::workerEnvironment($index),
            );
            if ($process === false) {
                throw new \RuntimeException("Could not start parallel worker $index.");
            }
            $workers[] = ['process' => $process, 'output' => $output, 'log' => $log, 'exitCode' => null];
        }

        return $workers;
    }

    /**
     * Every worker gets a unique token: MICROUNIT_WORKER, plus TEST_TOKEN — the convention
     * frameworks use to isolate parallel processes (Laravel's ParallelTesting, Storage::fake(),
     * per-process test databases).
     *
     * @return array<string, string>
     */
    private static function workerEnvironment(int $index): array
    {
        return [...getenv(), 'MICROUNIT_WORKER' => (string) $index, 'TEST_TOKEN' => (string) ($index + 1)];
    }

    /** @param list<array{process: resource, output: string, log: string, exitCode: ?int}> $workers */
    private function waitFor(array &$workers): void
    {
        do {
            $isAnyRunning = false;
            foreach ($workers as &$worker) {
                if ($worker['exitCode'] !== null) {
                    continue;
                }
                $status = proc_get_status($worker['process']);
                if ($status['running']) {
                    $isAnyRunning = true;
                } else {
                    $worker['exitCode'] = $status['exitcode'];
                    proc_close($worker['process']);
                }
            }
            unset($worker);
            if ($isAnyRunning) {
                usleep(self::POLL_INTERVAL_IN_MICROSECONDS);
            }
        } while ($isAnyRunning);
    }

    /**
     * Reads what WorkerRunner wrote: every test result serialized on its own, keyed by test id, and the
     * coverage. A result that cannot be read fails only its own test.
     *
     * @return WorkerResult|string the results, or why there are none
     */
    private function readResult(string $outputFile, TestPlan $share): WorkerResult|string
    {
        $contents = @file_get_contents($outputFile);
        if ($contents === false) {
            return 'It left no result.';
        }
        [$transport, $error] = self::unserialize($contents, []);
        if (!is_array($transport) || !is_array($transport['results'] ?? null) || !is_string($transport['coverage'] ?? null)) {
            return 'Its result could not be read: ' . ($error ?? 'unexpected format') . '.';
        }

        $testsById = array_column(array_map(static fn(array $entry): Test => $entry[1], $share->entries), null, 'id');
        $results = [];
        foreach ($transport['results'] as $id => $serialized) {
            $test = $testsById[$id] ?? null;
            if ($test === null) {
                continue;
            }
            [$result, $error] = is_string($serialized) ? self::unserialize($serialized, self::TRANSPORT_CLASSES) : [null, 'not serialized'];
            $results[] = $result instanceof TestResult
                ? $result
                : self::failedResult($test, "The parallel worker ran this test, but its result could not be read: $error");
        }
        [$coverage] = self::unserialize($transport['coverage'], [CoverageData::class]);

        return new WorkerResult($results, $coverage instanceof CoverageData ? $coverage : null);
    }

    /**
     * @param list<class-string> $allowedClasses
     * @return array{mixed, ?string} the value, and why it could not be read (with the bytes around the error)
     */
    private static function unserialize(string $contents, array $allowedClasses): array
    {
        error_clear_last();
        try {
            $value = @unserialize($contents, ['allowed_classes' => $allowedClasses]);
        } catch (\Throwable $throwable) {
            return [null, $throwable->getMessage()];
        }
        if ($value !== false || $contents === serialize(false)) {
            return [$value, null];
        }
        $error = error_get_last()['message'] ?? 'unserialize() failed';
        $near = preg_match('/at offset (\d+)/', $error, $match) === 1
            ? ', near ' . json_encode(substr($contents, max(0, (int) $match[1] - 200), 300), JSON_INVALID_UTF8_SUBSTITUTE)
            : '';

        return [null, $error . $near];
    }

    /** A worker that died (fatal error, exit() in a test, ...) fails every test it was given, with its log. */
    private function crashResult(TestPlan $share, string $logFile, ?int $exitCode, string $reason): WorkerResult
    {
        $log = trim((string) @file_get_contents($logFile));
        $message = "The parallel worker running this test stopped unexpectedly (exit code $exitCode). $reason"
            . ($log === '' ? '' : "\nWorker output:\n" . substr($log, -2000));

        return new WorkerResult(array_map(static fn(array $entry): TestResult => self::failedResult($entry[1], $message), $share->entries), null);
    }

    private static function failedResult(Test $test, string $message): TestResult
    {
        // Located at the test itself: a trace of this process would only show MicroUnit internals.
        $error = new TestError(\RuntimeException::class, $message, (string) $test->file, (int) $test->line, '');

        return new TestResult($test->id, $test->suite, $test->name, TestStatus::Failed, 0.0, error: $error, file: $test->file, line: $test->line, failureLine: $test->line);
    }
}
