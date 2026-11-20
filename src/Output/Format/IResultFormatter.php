<?php

namespace MicroUnit\Output\Format;

use MicroUnit\Core\TestResult;

/** Turns run events into text. Writers decide where the text goes (console, file, ...). */
interface IResultFormatter
{
    public function suite(string $suite): string;

    public function result(TestResult $result): string;

    public function summary(int $totalTests, int $successes, int $failures, float $timeInSeconds): string;
}
