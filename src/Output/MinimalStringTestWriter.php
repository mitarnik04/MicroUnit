<?php

namespace MicroUnit\Output;

use MicroUnit\Helpers\StringFormatter;
use MicroUnit\Core\TestResult;

class MinimalStringTestWriter implements ITestWriter
{
    public function writeResult(TestResult $testResult): void
    {
        $status = $testResult->isSuccess ? '✔' : '✖';
        $name = $testResult->testName;
        $time = isset($testResult->time) ? number_format($testResult->time * 1000, 2) . 'ms' : 'N/A';

        echo "[{$status}] {$name} ({$time})", PHP_EOL;

        if (!$testResult->isSuccess) {
            echo StringFormatter::formatLabelledBlock('Error: ' . $testResult->errorMsg ?? 'Unknown Error');
        }

        echo str_repeat(PHP_EOL, 2);
    }

    public function writeResults(array $results): void
    {
        foreach ($results as $result) {
            $this->writeResult($result);
        }
    }

    public function writeSummary(int $totalTests, int $successes, int $failures): void
    {
        echo PHP_EOL, "Summary: {$successes}/{$totalTests} passed, {$failures} failed", PHP_EOL;
    }

    public function writeSuite(string $suite): void
    {
        echo "-- {$suite} --", PHP_EOL;
    }
}
