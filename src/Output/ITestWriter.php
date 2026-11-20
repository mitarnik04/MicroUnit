<?php

namespace MicroUnit\Output;

use MicroUnit\Core\TestResult;

/** Receives test results; implement it to output results in your own format. */
interface ITestWriter
{
    /** Writes one result. Not called by MicroUnit directly — meant to be used by writeResults(). */
    public function writeResult(TestResult $result): void;

    /**
     * Called once per suite, after the suite finished.
     *
     * @param list<TestResult> $results
     */
    public function writeResults(array $results): void;

    /** Called once after all tests ran. */
    public function writeSummary(int $totalTests, int $successes, int $failures): void;

    /** Called before writeResults() for each suite. */
    public function writeSuite(string $suite): void;
}
