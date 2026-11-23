<?php

namespace MicroUnit\Parallel;

use MicroUnit\Assertion\AssertionFailure;
use MicroUnit\Assertion\NotProvided;
use MicroUnit\Core\RunSummary;
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

    /** Classes a worker result may contain; nothing else is ever unserialized. */
    private const array TRANSPORT_CLASSES = [
        WorkerResult::class, TestResult::class, TestStatus::class, TestError::class, AssertionFailure::class,
        NotProvided::class, Diff::class, DiffLine::class, DiffLineType::class, ExportedValue::class, CoverageData::class,
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
            $result = $this->readResult($worker['output']);
            if (is_string($result)) {
                $result = $this->crashResult($plan->partition($index, $this->workerCount), $worker['log'], $worker['exitCode'], $result);
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

    /** @return WorkerResult|string the result, or why there is none */
    private function readResult(string $outputFile): WorkerResult|string
    {
        $contents = @file_get_contents($outputFile);
        if ($contents === false) {
            return 'It left no result.';
        }
        try {
            $result = @unserialize($contents, ['allowed_classes' => self::TRANSPORT_CLASSES]);
        } catch (\Throwable $error) {
            return 'Its result could not be read: ' . $error->getMessage();
        }

        if ($result instanceof WorkerResult) {
            return $result;
        }
        $error = error_get_last()['message'] ?? get_debug_type($result);
        $near = preg_match('/at offset (\d+)/', $error, $match) === 1
            ? "\nNear: " . json_encode(substr($contents, max(0, (int) $match[1] - 200), 300), JSON_INVALID_UTF8_SUBSTITUTE)
            : '';

        return 'Its result could not be read (' . strlen($contents) . " bytes): $error$near";
    }

    /** A worker that died (fatal error, exit() in a test, ...) fails every test it was given, with its log. */
    private function crashResult(TestPlan $share, string $logFile, ?int $exitCode, string $reason): WorkerResult
    {
        $log = trim((string) @file_get_contents($logFile));
        $message = "The parallel worker running this test stopped unexpectedly (exit code $exitCode). $reason"
            . ($log === '' ? '' : "\nWorker output:\n" . substr($log, -2000));

        $results = [];
        foreach ($share->entries as [, $test]) {
            // Located at the test itself: a trace of this process would only show MicroUnit internals.
            $error = new TestError(\RuntimeException::class, $message, (string) $test->file, (int) $test->line, '');
            $results[] = new TestResult($test->id, $test->suite, $test->name, TestStatus::Failed, 0.0, error: $error, file: $test->file, line: $test->line, failureLine: $test->line);
        }

        return new WorkerResult($results, null);
    }
}
