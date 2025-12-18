<?php

namespace MicroUnit\Output;

use MicroUnit\Core\TestResult;
use MicroUnit\Assertion\AssertionFailure;
use MicroUnit\Helpers\DiffFormatter;
use MicroUnit\Helpers\StringFormatter;
use MicroUnit\Helpers\ValueExporter;

class StringTestWriter implements ITestWriter
{
    public function writeResult(TestResult $testResult): void
    {
        $this->writeHeader($testResult);

        if ($testResult->assertionFailure) {
            $this->writeFailure($testResult->assertionFailure);
        }

        echo 'Time: '
            . (isset($testResult->time) ? number_format($testResult->time * 1000, 2) . 'ms' : 'N/A')
            . PHP_EOL;


        if (!$testResult->isSuccess) {
            $this->writeStacktrace($testResult->exception ?? null);
        } else {
            echo 'Stack trace: N/A', PHP_EOL;
        }

        echo str_repeat('-', 80), PHP_EOL;
    }

    public function writeResults(array $results): void
    {
        foreach ($results as $result) {
            $this->writeResult($result);
        }
    }

    public function writeSummary(int $totalTests, int $successes, int $failures): void
    {
        echo PHP_EOL, str_repeat('=', 33), " Test Summary ", str_repeat('=', 34),  PHP_EOL;
        echo "Total: {$totalTests}", PHP_EOL;
        echo "Succeeded: {$successes}", PHP_EOL;
        echo "Failed: {$failures}", PHP_EOL;
        echo str_repeat('=', 80), PHP_EOL;
    }

    public function writeSuite(string $suite): void
    {
        echo str_repeat('=', 80),  PHP_EOL;
        echo str_repeat(' ', 18), "Test Suite: $suite ",  PHP_EOL;
        echo str_repeat('=', 80), PHP_EOL;
    }

    private function writeHeader(TestResult $testResult): void
    {
        echo StringFormatter::formatLabelledBlock('Test: ' . $testResult->testName), PHP_EOL;
        echo StringFormatter::formatLabelledBlock('Success: ' . ($testResult->isSuccess ? 'Yes' : 'No')), PHP_EOL;
        echo StringFormatter::formatLabelledBlock('Result: ' . ValueExporter::export($testResult->result)), PHP_EOL;
        if (!$testResult->assertionFailure) {
            echo StringFormatter::formatLabelledBlock('Error: ' . ($testResult->errorMsg ?? 'None')), PHP_EOL;
        }
    }

    private function writeStacktrace(?\Throwable $exception): void
    {
        if ($exception) {
            echo 'Stack trace: ', $exception::class, PHP_EOL, $exception->getTraceAsString(), PHP_EOL;
        } else {
            echo 'Stack trace: N/A', PHP_EOL;
        }
    }

    private function writeFailure(AssertionFailure $failure): void
    {
        echo 'Error: ', $failure->message, PHP_EOL;

        if ($failure->diff) {
            echo DiffFormatter::toString($failure->diff);
        } else {
            if ($failure->expected !== null) {
                echo 'Expected: ', ValueExporter::export($failure->expected), PHP_EOL;
            }
            if ($failure->actual !== null) {
                echo 'Actual: ', ValueExporter::export($failure->actual), PHP_EOL;
            }
        }

        if (!empty($failure->metadata)) {
            echo 'Metadata: ', ValueExporter::export($failure->metadata), PHP_EOL;
        }
    }
}
