<?php
require_once __DIR__ . '/../test-result.php';
require_once __DIR__ . '/test-writer.php';

class FileTestWriter implements ITestWriter
{
    private string $filePath;

    public function __construct(string $filePath = __DIR__ . '/../../test-results.log')
    {
        $this->filePath = $filePath;
    }

    public function writeResult(TestResult $testResult): void
    {
        $output = $this->getHeader($testResult);

        if (isset($testResult->time)) {
            $output .= 'Time: ' . number_format($testResult->time * 1000, 4) . "ms\n";
        } else {
            $output .= "Time: N/A\n";
        }

        if ($testResult->isError) {
            $output .= $this->getStacktrace($testResult->exception ?? null);
        } else {
            $output .= "Stack trace: N/A\n";
        }

        $output .= str_repeat('-', 80) . "\n";
        file_put_contents($this->filePath, $output, FILE_APPEND);
    }

    public function writeResults(array $results): void
    {
        foreach ($results as $result) {
            $this->writeResult($result);
        }
    }

    public function writeSummary(int $totalTests, int $successes, int $failures): void
    {
        $summary = "\n" . str_repeat('=', 33) . " Test Summary " . str_repeat('=', 34) . "\n";
        $summary .= "Total: {$totalTests}\n";
        $summary .= "Succeeded: {$successes}\n";
        $summary .= "Failed: {$failures}\n";
        $summary .= str_repeat('=', 80) . "\n";
        file_put_contents($this->filePath, $summary, FILE_APPEND);
    }

    public function writeSuite(string $suite): void
    {
        $header = str_repeat('=', 80) . "\n";
        $header .= str_repeat(' ', 18) . "Test Suite: $suite \n";
        $header .= str_repeat('=', 80) . "\n";
        file_put_contents($this->filePath, $header, FILE_APPEND);
    }

    private function getHeader(TestResult $testResult): string
    {
        return sprintf(
            "Test: %s\nSuccess: %s\nResult: %s\nError: %s\n",
            $testResult->testName,
            $testResult->isSuccess ? 'Yes' : 'No',
            var_export($testResult->result, true),
            $testResult->errorMsg ?? 'None'
        );
    }

    private function getStacktrace(?Throwable $exception): string
    {
        if ($exception) {
            return 'Stack trace: ' . $exception::class . "\n" . $exception->getTraceAsString() . "\n";
        }
        return "Stack trace: N/A\n";
    }
}
