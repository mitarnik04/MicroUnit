<?php

namespace MicroUnit\Output;

use MicroUnit\Assertion\AssertionFailure;
use MicroUnit\Core\RunSummary;
use MicroUnit\Core\TestError;
use MicroUnit\Core\TestResult;
use MicroUnit\Core\TestStatus;
use MicroUnit\Helpers\DiffLine;
use MicroUnit\Helpers\DiffLineType;
use MicroUnit\Helpers\Filesystem;
use MicroUnit\Helpers\ValueExporter;

/**
 * Output for AI coding agents: one compact JSON object on stdout, with the failures only. Each
 * failure carries what an agent needs to fix it and nothing else: where it failed, expected and
 * actual (or a diff), the lines of code around the failure and a stack trace of the project's own
 * code. Passing tests cost no tokens.
 */
final class AgentTestWriter implements ITestWriter
{
    private const int CODE_CONTEXT_LINES = 2;

    private const int MAXIMUM_VALUE_LENGTH = 1500;

    private const int MAXIMUM_TRACE_FRAMES = 8;

    private const int DIFF_CONTEXT_LINES = 2;

    /** @var list<TestResult> */
    private array $results = [];

    private readonly string $projectDirectory;

    private readonly ?\Closure $integrityFindings;

    private readonly string $integrityRevision;

    private readonly ?\Closure $runFailures;

    /**
     * @param string|null $filePath write the JSON to this file instead of stdout (relative to the working directory)
     * @param string|null $projectDirectory paths in the output are relative to it (default: the working directory)
     */
    public function __construct(
        private readonly ?string $filePath = null,
        ?string $projectDirectory = null,
    ) {
        $this->projectDirectory = rtrim(str_replace('\\', '/', $projectDirectory ?? (string) getcwd()), '/') . '/';
        $this->integrityFindings = null;
        $this->integrityRevision = 'HEAD';
        $this->runFailures = null;
    }

    /**
     * The same writer, reporting tests weakened compared to $revision as well.
     *
     * @param \Closure(): list<array{test: string, file: string, line: int, finding: string}> $findings
     */
    public function withIntegrityFindings(\Closure $findings, string $revision = 'HEAD'): self
    {
        return clone($this, ['integrityFindings' => $findings, 'integrityRevision' => $revision]);
    }

    /**
     * The same writer, also failing the run for reasons other than failed tests (a coverage minimum,
     * failOnRiskyTests ...), so "result" always agrees with the exit code.
     *
     * @param \Closure(): list<string> $failures
     */
    public function withRunFailures(\Closure $failures): self
    {
        return clone($this, ['runFailures' => $failures]);
    }

    /** True when the JSON goes to stdout, where the agent reads it. */
    public function writesToConsole(): bool
    {
        return $this->filePath === null;
    }

    /** True when an AI agent runs the tests: Claude Code sets CLAUDECODE=1; MICROUNIT_AGENT=1 works for any agent. */
    public static function isAgentSession(): bool
    {
        return getenv('CLAUDECODE') === '1' || getenv('MICROUNIT_AGENT') === '1';
    }

    #[\Override]
    public function writeResult(TestResult $result): void
    {
        $this->results[] = $result;
    }

    #[\Override]
    public function writeResults(array $results): void
    {
        array_push($this->results, ...$results);
    }

    #[\Override]
    public function writeSuite(string $suite): void {}

    #[\Override]
    public function writeSummary(int $totalTests, int $successes, int $failures): void
    {
        $skipped = array_values(array_filter($this->results, static fn(TestResult $result): bool => $result->status === TestStatus::Skipped));
        $runFailures = $this->runFailures === null ? [] : ($this->runFailures)();
        $report = [
            'result' => $failures > 0 || $runFailures !== [] ? 'failed' : 'passed',
            'tests' => $totalTests,
            'passed' => $successes,
            'failed' => $failures,
            'skipped' => count($skipped),
            'seconds' => round(RunSummary::elapsedSeconds(), 3),
        ];
        $failed = array_values(array_filter($this->results, static fn(TestResult $result): bool => $result->status === TestStatus::Failed));
        if ($runFailures !== []) {
            $report['failedBecause'] = $runFailures;
        }
        if ($failed !== []) {
            $report['failures'] = array_map($this->failure(...), $failed);
        }
        $risky = array_values(array_filter($this->results, static fn(TestResult $result): bool => $result->isRisky()));
        if ($risky !== []) {
            $report['warnings'] = array_map(static fn(TestResult $result): array => ['test' => $result->id, 'warning' => 'passed without a single assertion'], $risky);
        }
        $integrity = $this->integrityFindings === null ? [] : ($this->integrityFindings)();
        if ($integrity !== []) {
            $report['weakenedTests'] = array_map(static fn(array $finding): array => ['test' => $finding['test'], 'at' => "{$finding['file']}:{$finding['line']}", 'finding' => $finding['finding']], $integrity);
            $compared = $this->integrityRevision === 'HEAD' ? 'the last commit' : $this->integrityRevision;
            $report['weakenedTestsNote'] = "Compared to $compared. Tests must not be weakened to make them pass: fix the code, or explain why the test changed.";
        }
        if ($skipped !== []) {
            $report['skippedTests'] = array_map(static fn(TestResult $result): array => ['test' => $result->id, 'reason' => $result->skipReason], $skipped);
        }

        $json = json_encode($report, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR) . PHP_EOL;
        if ($this->filePath === null) {
            echo $json;
        } else {
            Filesystem::writeAtomically(Filesystem::resolve((string) getcwd(), $this->filePath), $json);
        }
    }

    /** @return array<string, mixed> */
    private function failure(TestResult $result): array
    {
        $line = $result->failureLine ?? $result->line;
        $entry = ['test' => $result->id];
        if ($result->file !== null && $line !== null) {
            $entry['at'] = $this->relative($result->file) . ':' . $line;
        }

        if ($result->assertionFailure !== null) {
            $entry += $this->assertion($result->assertionFailure);
        } elseif ($result->error !== null) {
            $entry += $this->error($result->error, $result->file);
        }
        if ($result->file !== null && $line !== null) {
            $entry['code'] = $this->code($result->file, $line);
        }
        if ($result->output !== '') {
            $entry['output'] = self::shortened(trim($result->output));
        }

        return $entry;
    }

    /** @return array<string, mixed> */
    private function assertion(AssertionFailure $failure): array
    {
        $entry = ['message' => str_replace("\r\n", "\n", $failure->fullMessage())];
        if ($failure->diff !== null && count($failure->diff->lines) > 2) {
            $entry['diff'] = $this->compactDiff($failure);

            return $entry;
        }
        if ($failure->hasExpected()) {
            $entry['expected'] = ValueExporter::export($failure->expected, self::MAXIMUM_VALUE_LENGTH);
        }
        if ($failure->hasActual()) {
            $entry['actual'] = ValueExporter::export($failure->actual, self::MAXIMUM_VALUE_LENGTH);
        }

        return $entry;
    }

    /** @return array<string, mixed> */
    private function error(TestError $error, ?string $testFile): array
    {
        $entry = ['error' => "{$error->class}: {$error->message}"];
        if ($error->file !== $testFile) {
            $entry['thrownAt'] = $this->relative($error->file) . ':' . $error->line;
        }
        $frames = $this->userFrames($error->trace);
        if ($frames !== []) {
            $entry['trace'] = $frames;
        }

        return $entry;
    }

    /** Changed lines with a little context; long runs of equal lines are folded. @return list<string> */
    private function compactDiff(AssertionFailure $failure): array
    {
        return array_map(static fn(?DiffLine $line): string => match ($line?->type) {
            null => '…',
            DiffLineType::Same => '  ' . $line->line,
            DiffLineType::ExpectedOnly => '- ' . $line->line,
            DiffLineType::ActualOnly => '+ ' . $line->line,
        }, $failure->diff?->changesWithContext(self::DIFF_CONTEXT_LINES) ?? []);
    }

    /** The failing line and its neighbours, the failing one marked with ">". @return list<string> */
    private function code(string $file, int $line): array
    {
        $source = @file($file, FILE_IGNORE_NEW_LINES);
        if ($source === false) {
            return [];
        }
        $code = [];
        for ($number = max(1, $line - self::CODE_CONTEXT_LINES); $number <= min(count($source), $line + self::CODE_CONTEXT_LINES); $number++) {
            $code[] = $number . ($number === $line ? '> ' : '| ') . rtrim($source[$number - 1]);
        }

        // Blank lines at the edges carry no information.
        while ($code !== [] && preg_match('/^\d+\| $/', $code[array_key_last($code)])) {
            array_pop($code);
        }
        while ($code !== [] && preg_match('/^\d+\| $/', $code[0])) {
            array_shift($code);
        }

        return $code;
    }

    /** Stack frames in the project's own code (no vendor/, no MicroUnit internals). @return list<string> */
    private function userFrames(string $trace): array
    {
        $microUnit = str_replace('\\', '/', dirname(__DIR__)) . '/';
        $frames = [];
        foreach (explode("\n", $trace) as $frame) {
            if (!preg_match('/^#\d+ (.+?)\((\d+)\): (.+)$/', $frame, $match)) {
                continue;
            }
            $file = str_replace('\\', '/', $match[1]);
            if (str_starts_with($file, $microUnit) || str_contains($file, '/vendor/') || !str_starts_with($file, $this->projectDirectory)) {
                continue;
            }
            $frames[] = $this->relative($file) . ":{$match[2]} {$match[3]}";
            if (count($frames) === self::MAXIMUM_TRACE_FRAMES) {
                break;
            }
        }

        return $frames;
    }

    private function relative(string $file): string
    {
        return Filesystem::relativeTo($file, $this->projectDirectory);
    }

    private static function shortened(string $text): string
    {
        return strlen($text) > self::MAXIMUM_VALUE_LENGTH ? substr($text, 0, self::MAXIMUM_VALUE_LENGTH) . '… (' . strlen($text) . ' bytes)' : $text;
    }
}
