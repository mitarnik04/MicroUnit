<?php

namespace MicroUnit\Core;

use MicroUnit\Assertion\AssertionFailure;
use MicroUnit\Exceptions\TestFailedException;

class TestResult
{
    private function __construct(
        public readonly string $testName,
        public readonly bool $isSuccess,
        public readonly ?string $errorMsg = null,
        public readonly mixed $result = null,
        public readonly ?float $time = null,
        public readonly ?\Exception $exception = null,
        public readonly ?AssertionFailure $assertionFailure = null,
    ) {}

    public static function success(string $testName, $result = null, ?float $time = null): TestResult
    {
        return new TestResult($testName, true, time: $time, result: $result);
    }

    public static function failure(string $testName, string $errorMsg, ?float $time = null): TestResult
    {
        return new TestResult($testName, false, $errorMsg, time: $time);
    }

    public static function failureFromException(string $testName, \Exception $exception, ?float $time = null): TestResult
    {
        return $exception instanceof TestFailedException
            ? new TestResult($testName, false, time: $time, assertionFailure: $exception->failure)
            :  new TestResult($testName, false, $exception->getMessage(), time: $time, exception: $exception);
    }
}
