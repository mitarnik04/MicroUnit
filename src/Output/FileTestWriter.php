<?php

namespace MicroUnit\Output;

use MicroUnit\Assertion\AssertionFailure;
use MicroUnit\Core\TestResult;
use MicroUnit\Helpers\DiffFormatter;
use MicroUnit\Helpers\StringFormatter;
use MicroUnit\Helpers\ValueExporter;

class FileTestWriter implements ITestWriter
{
    private string $filePath;

    public function __construct(string $filePath = __DIR__ . '/../../test-results.log', bool $clearFileBeforeRun = false)
    {
        if ($clearFileBeforeRun) {
            file_put_contents($filePath, '');
        }
        $this->filePath = $filePath;
    }

    public function writeResult(TestResult $testResult): void
    {
        $status = $testResult->isSuccess ? '✔' : '✖';
        $name = $testResult->testName;
        $time = isset($testResult->time) ? number_format($testResult->time * 1000, 2) . 'ms' : 'N/A';

        $content = "[{$status}] {$name} ({$time})" . PHP_EOL;

        if (!$testResult->isSuccess) {
            if ($testResult->assertionFailure) {
                $content .= $this->writeFailure($testResult->assertionFailure);
            } else {
                $content .= StringFormatter::formatLabelledBlock('Error: ' . $testResult->errorMsg ?? 'Unknown Error');
            }
        }

        $content .= str_repeat(PHP_EOL, 2);
        file_put_contents($this->filePath, $content, FILE_APPEND);
    }

    public function writeResults(array $results): void
    {
        foreach ($results as $result) {
            $this->writeResult($result);
        }
    }

    public function writeSummary(int $totalTests, int $successes, int $failures): void
    {
        file_put_contents($this->filePath, PHP_EOL . "Summary: {$successes}/{$totalTests} passed, {$failures} failed" . PHP_EOL, FILE_APPEND);
    }

    public function writeSuite(string $suite): void
    {
        file_put_contents($this->filePath, "-- {$suite} --" . PHP_EOL, FILE_APPEND);
    }

    private function writeFailure(AssertionFailure $failure): string
    {
        $result = $failure->message . PHP_EOL;

        if ($failure->diff) {
            $result .= DiffFormatter::toString($failure->diff);
        } else {
            if ($failure->expected !== null) {
                $result .= 'Expected: ' . ValueExporter::export($failure->expected) . PHP_EOL;
            }
            if ($failure->actual !== null) {
                $result .= 'Actual: ' . ValueExporter::export($failure->actual) . PHP_EOL;
            }
        }

        return $result;
    }
}
