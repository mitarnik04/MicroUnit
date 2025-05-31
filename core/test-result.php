<?php

class TestResult
{
    public readonly bool $isError;

    private function __construct(
        public readonly string $testName,
        public readonly bool $isSuccess,
        public readonly ?string $errorMsg = null,
        public readonly mixed $result = null,
        public readonly ?float $time = null,
        public readonly ?Exception $exception = null,
    ) {
        $this->isError = isset($errorMsg);
    }

    public static function success(string $testName, $result = null, ?float $time = null): TestResult
    {
        return new TestResult($testName, true, time: $time, result: $result);
    }

    public static function failiure(string $testName, string $errorMsg, ?float $time = null): TestResult
    {
        return new TestResult($testName, false, $errorMsg, time: $time);
    }

    public static function failiureFromException(string $testName, Exception $exception, ?float $time = null): TestResult
    {
        return new TestResult($testName, false, $exception->getMessage(), time: $time, exception: $exception);
    }
}
