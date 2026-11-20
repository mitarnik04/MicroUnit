<?php

namespace MicroUnit\Core;

use MicroUnit\Assertion\AssertionFailure;
use MicroUnit\Assertion\ForeignAssertionFailure;
use MicroUnit\Exceptions\TestFailedException;
use MicroUnit\Helpers\ExportedValue;

/** Outcome of one test. */
final readonly class TestResult
{
    /** True unless the test failed (a skipped test is not a failure). */
    public bool $isSuccess;

    /** Failure description or skip reason; null for passed tests. */
    public ?string $errorMsg;

    /**
     * @param float $time duration of the test body in seconds
     * @param mixed $result the test function's return value
     * @param int|null $failureLine the line of the test file where the failure surfaced
     */
    public function __construct(
        public string $id,
        public string $suite,
        public string $testName,
        public TestStatus $status,
        public float $time,
        public mixed $result = null,
        public ?AssertionFailure $assertionFailure = null,
        public ?TestError $error = null,
        public string $output = '',
        public ?string $file = null,
        public ?int $line = null,
        public ?int $failureLine = null,
        public ?string $skipReason = null,
        public int $assertions = 0,
    ) {
        $this->isSuccess = $status !== TestStatus::Failed;
        $this->errorMsg = $assertionFailure?->fullMessage() ?? $error?->message ?? $skipReason;
    }

    public static function passed(Test $test, float $time, mixed $result = null): self
    {
        return new self($test->id, $test->suite, $test->name, TestStatus::Passed, $time, $result, file: $test->file, line: $test->line);
    }

    /** @param float $time how long the test ran before it skipped itself; 0 when it never started */
    public static function skipped(Test $test, string $reason, float $time = 0.0): self
    {
        return new self($test->id, $test->suite, $test->name, TestStatus::Skipped, $time, file: $test->file, line: $test->line, skipReason: $reason);
    }

    public static function failed(Test $test, float $time, \Throwable $throwable): self
    {
        $assertionFailure = $throwable instanceof TestFailedException
            ? $throwable->failure
            : ForeignAssertionFailure::from($throwable);

        return new self(
            $test->id,
            $test->suite,
            $test->name,
            TestStatus::Failed,
            $time,
            assertionFailure: $assertionFailure,
            error: $assertionFailure === null ? TestError::fromThrowable($throwable) : null,
            file: $test->file,
            line: $test->line,
            failureLine: self::lineInFile($throwable, $test->file) ?? $test->line,
        );
    }

    public function isSkipped(): bool
    {
        return $this->status === TestStatus::Skipped;
    }

    /** What the runner learns at the very end of a test, in one copy. */
    public function withRunDetails(string $output, int $assertions): self
    {
        return clone($this, ['output' => $output, 'assertions' => $assertions]);
    }

    /** A passing test that asserted nothing proves nothing: worth a warning. */
    public function isRisky(): bool
    {
        return $this->status === TestStatus::Passed && $this->assertions === 0;
    }

    /** Copy without values that cannot be serialized (closures, resources): compared and returned values become text. */
    public function forTransport(): self
    {
        return clone($this, [
            'result' => $this->result === null ? null : ExportedValue::of($this->result),
            'assertionFailure' => $this->assertionFailure?->withExportedValues(),
        ]);
    }

    /** The line of $file where the failure surfaced (e.g. the failing assertion), if the trace passes through it. */
    private static function lineInFile(\Throwable $throwable, ?string $file): ?int
    {
        if ($file === null) {
            return null;
        }
        if ($throwable->getFile() === $file) {
            return $throwable->getLine();
        }
        $frame = array_find($throwable->getTrace(), static fn(array $frame): bool => ($frame['file'] ?? null) === $file);

        return $frame['line'] ?? null;
    }
}
