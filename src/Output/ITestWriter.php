<?php

namespace MicroUnit\Output;

use MicroUnit\Core\TestResult;

interface ITestWriter
{
    function writeResult(TestResult $result): void;

    //TODO: Make this a general implementation in base class ? 
    /** @param array<TestResult> $results */
    function writeResults(array $results): void;

    /** @param array<TestResult> $results */
    function writeSummary(int $totalTests, int $successes, int $failures): void;

    function writeSuite(string $suite): void;
}
