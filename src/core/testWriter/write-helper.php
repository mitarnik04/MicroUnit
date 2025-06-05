<?php
require_once __DIR__ . '/test-writer.php';


class WriteHelper
{
    /** @param array<ITestWriter> */
    public function __construct(
        public readonly array $writers
    ) {}

    function writeResult(TestResult $result): void
    {
        foreach ($this->writers as $writer) {
            $writer->writeResult($result);
        }
    }

    /** @param array<TestResult> $results */
    function writeResults(array $results): void
    {
        foreach ($this->writers as $writer) {
            $writer->writeResults($results);
        }
    }

    /** @param array<TestResult> $results */
    function writeSummary(int $totalTests, int $successes, int $failures): void
    {
        foreach ($this->writers as $writer) {
            $writer->writeSummary($totalTests, $successes, $failures);
        }
    }

    function writeSuite(string $suite): void
    {
        foreach ($this->writers as $writer) {
            $writer->writeSuite($suite);
        }
    }
}
