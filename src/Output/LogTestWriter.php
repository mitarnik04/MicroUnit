<?php

namespace MicroUnit\Output;

use MicroUnit\Core\TestResult;
use MicroUnit\Core\TestStatus;
use MicroUnit\Helpers\Filesystem;

/**
 * Structured log of a test run. The line format is a template so the log can match the format of
 * the other logs in your system; LogTestWriter::jsonLines() writes one JSON object per line instead.
 *
 * Placeholders: {datetime} {level} {status} {suite} {test} {id} {duration_ms} {message} {failure} {file} {line}
 * {datetime} is when the line is written: results arrive per suite, after the suite finished (in a
 * parallel run, after all workers finished), so it is not the moment a single test ran.
 */
final class LogTestWriter implements ITestWriter
{
    public const string DEFAULT_LINE_FORMAT = '[{datetime}] microunit.{level}: {status} {suite} > {test} ({duration_ms} ms){failure}';

    private readonly string $filePath;

    private string $buffer = '';

    private int $runTimeInMs = 0;

    /**
     * @param string $filePath absolute, or relative to the current working directory
     * @param string $dateFormat any date() format
     * @param bool $logPassedTests false logs only failures (and the summary)
     */
    public function __construct(
        string $filePath = 'microunit.log',
        private readonly string $lineFormat = self::DEFAULT_LINE_FORMAT,
        private readonly string $dateFormat = 'Y-m-d\TH:i:sP',
        private readonly bool $logPassedTests = true,
        private readonly bool $isJsonLines = false,
    ) {
        $this->filePath = Filesystem::resolve((string) getcwd(), $filePath);
    }

    /** One JSON object per line — ready for log shippers like Filebeat, Fluent Bit or Vector. */
    public static function jsonLines(string $filePath = 'microunit.jsonl', bool $logPassedTests = true): self
    {
        return new self($filePath, logPassedTests: $logPassedTests, isJsonLines: true);
    }

    #[\Override]
    public function writeResult(TestResult $result): void
    {
        $durationInMs = (int) round($result->time * 1000);
        $this->runTimeInMs += $durationInMs;
        if ($result->status === TestStatus::Passed && !$this->logPassedTests) {
            return;
        }

        $this->append([
            'datetime' => date($this->dateFormat),
            'level' => match ($result->status) {
                TestStatus::Passed => 'INFO', TestStatus::Skipped => 'WARNING', TestStatus::Failed => 'ERROR',
            },
            'status' => strtoupper($result->status->value),
            'suite' => $result->suite,
            'test' => $result->testName,
            'id' => $result->id,
            'duration_ms' => $durationInMs,
            'message' => $result->errorMsg === null ? '' : self::singleLine($result->errorMsg),
            'file' => $result->file ?? '',
            'line' => $result->failureLine ?? $result->line ?? '',
        ]);
    }

    #[\Override]
    public function writeResults(array $results): void
    {
        foreach ($results as $result) {
            $this->writeResult($result);
        }
    }

    #[\Override]
    public function writeSummary(int $totalTests, int $successes, int $failures): void
    {
        $this->append([
            'datetime' => date($this->dateFormat),
            'level' => $failures > 0 ? 'ERROR' : 'NOTICE',
            'status' => 'SUMMARY',
            'suite' => '',
            'test' => '',
            'id' => '',
            'duration_ms' => $this->runTimeInMs,
            'message' => "$successes/$totalTests passed, $failures failed",
            'file' => '',
            'line' => '',
        ]);

        Filesystem::ensureDirectory(dirname($this->filePath));
        file_put_contents($this->filePath, $this->buffer, FILE_APPEND | LOCK_EX);
        $this->buffer = '';
    }

    #[\Override]
    public function writeSuite(string $suite): void {}

    /** @param array<string, string|int> $fields */
    private function append(array $fields): void
    {
        if ($this->isJsonLines) {
            $this->buffer .= json_encode($fields, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) . PHP_EOL;
            return;
        }

        $fields['failure'] = $fields['message'] === '' ? '' : ' - ' . $fields['message'];
        $replacements = [];
        foreach ($fields as $key => $value) {
            $replacements['{' . $key . '}'] = (string) $value;
        }
        $this->buffer .= strtr($this->lineFormat, $replacements) . PHP_EOL;
    }

    private static function singleLine(string $text): string
    {
        return trim(preg_replace('/\s*\R\s*/', ' | ', $text) ?? $text);
    }
}
