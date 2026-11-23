<?php

namespace MicroUnit\Assertion;

use MicroUnit\Core\TestContext;
use MicroUnit\Helpers\Diff;
use MicroUnit\Mock\CallLog;
use MicroUnit\Mock\MicroMock;
use MicroUnit\Mock\MockRegistry;

/**
 * Fluent assertions about the calls a mock received. Every check counts as one assertion and
 * fails like any Assert method (so Assert::multiple() collects it).
 *
 *     AssertMock::begin($mock)      // calls on every instance the mock created
 *     AssertMock::begin($instance)  // calls on this one instance only
 */
final readonly class AssertMock
{
    private function __construct(
        private CallLog $callLog,
        private string $subject,
    ) {}

    /** @param MicroMock|object $mockOrInstance */
    public static function begin(object $mockOrInstance): self
    {
        if ($mockOrInstance instanceof MicroMock) {
            return new self($mockOrInstance->getCallLog(), self::shortName($mockOrInstance->getTargetType()) . ' mock');
        }

        $owner = MockRegistry::ownerOf($mockOrInstance) ?? throw new \InvalidArgumentException(
            'AssertMock::begin() expects a MicroMock or an instance created by one, got ' . get_debug_type($mockOrInstance) . '.',
        );

        return new self($owner->getInstanceCallLog($mockOrInstance), self::shortName($owner->getTargetType()) . ' instance');
    }

    // ---- Call counts -------------------------------------------------------------------------

    public function isCalled(string $method): self
    {
        $count = $this->callLog->getCallCount($method);
        if (!self::passes($count > 0)) {
            $this->failCallCount($method, 'at least once', $count);
        }

        return $this;
    }

    public function isCalledTimes(string $method, int $expectedCallCount): self
    {
        $count = $this->callLog->getCallCount($method);
        if (!self::passes($count === $expectedCallCount)) {
            $this->failCallCount($method, "exactly $expectedCallCount time(s)", $count);
        }

        return $this;
    }

    public function isCalledOnce(string $method): self
    {
        return $this->isCalledTimes($method, 1);
    }

    public function isNotCalled(string $method): self
    {
        return $this->isCalledTimes($method, 0);
    }

    public function isCalledAtLeast(string $method, int $minCallCount): self
    {
        $count = $this->callLog->getCallCount($method);
        if (!self::passes($count >= $minCallCount)) {
            $this->failCallCount($method, "at least $minCallCount time(s)", $count);
        }

        return $this;
    }

    public function isCalledMoreThan(string $method, int $callCount): self
    {
        $count = $this->callLog->getCallCount($method);
        if (!self::passes($count > $callCount)) {
            $this->failCallCount($method, "more than $callCount time(s)", $count);
        }

        return $this;
    }

    public function isCalledAtMost(string $method, int $maxCallCount): self
    {
        $count = $this->callLog->getCallCount($method);
        if (!self::passes($count <= $maxCallCount)) {
            $this->failCallCount($method, "at most $maxCallCount time(s)", $count);
        }

        return $this;
    }

    public function isCalledLessThan(string $method, int $callCount): self
    {
        $count = $this->callLog->getCallCount($method);
        if (!self::passes($count < $callCount)) {
            $this->failCallCount($method, "less than $callCount time(s)", $count);
        }

        return $this;
    }

    // ---- Arguments (compared strictly with ===) ----------------------------------------------

    /**
     * At least one call received exactly these arguments.
     *
     * @param list<mixed> $expectedArgs
     */
    public function isCalledWith(string $method, array $expectedArgs): self
    {
        $allCallArgs = $this->callLog->getAllCallArgs($method);
        if (!self::passes(in_array($expectedArgs, $allCallArgs, true))) {
            $this->fail("Expected {$this->subject}: $method() to be called with the given arguments.", $expectedArgs, $allCallArgs);
        }

        return $this;
    }

    /**
     * @param list<mixed> $expectedArgs
     * @param int $callNumber 1-based
     */
    public function isCalledWithOnSpecificCall(string $method, array $expectedArgs, int $callNumber): self
    {
        $callArgs = $this->callLog->getCallArgs($method, $callNumber);
        if (!self::passes($callArgs === $expectedArgs)) {
            $this->fail(
                "Expected call #$callNumber of {$this->subject}: $method() to receive the given arguments.",
                $expectedArgs,
                $callArgs,
                Diff::generate($expectedArgs, $callArgs),
            );
        }

        return $this;
    }

    /**
     * Every call received exactly these arguments.
     *
     * @param list<mixed> $expectedArgs
     */
    public function isOnlyCalledWith(string $method, array $expectedArgs): self
    {
        $allCallArgs = $this->callLog->getAllCallArgs($method);
        if (!self::passes(!array_any($allCallArgs, static fn(array $args): bool => $args !== $expectedArgs))) {
            $this->fail("Expected every call of {$this->subject}: $method() to receive the given arguments.", $expectedArgs, $allCallArgs);
        }

        return $this;
    }

    /** @param callable(list<mixed> $args): bool $matcher must accept every call */
    public function isOnlyCalledWithMatchingArgs(string $method, callable $matcher): self
    {
        $allCallArgs = $this->callLog->getAllCallArgs($method);
        if (!self::passes(array_all($allCallArgs, static fn(array $args): bool => $matcher($args) === true))) {
            $this->fail("Expected every call of {$this->subject}: $method() to match the given matcher.", NotProvided::Value, $allCallArgs);
        }

        return $this;
    }

    /**
     * @param callable(list<mixed>|null $args): bool $matcher
     * @param int $callNumber 1-based
     */
    public function isCalledWithMatchingOnSpecificCall(string $method, callable $matcher, int $callNumber): self
    {
        $callArgs = $this->callLog->getCallArgs($method, $callNumber);
        if (!self::passes($matcher($callArgs) === true)) {
            $this->fail("Expected call #$callNumber of {$this->subject}: $method() to match the given matcher.", NotProvided::Value, $callArgs);
        }

        return $this;
    }

    // ---- Order -------------------------------------------------------------------------------

    /** $method was the n-th call (1-based) across all methods. */
    public function isCalledOn(string $method, int $callNumber): self
    {
        $sequence = $this->callLog->getCallSequence();
        if (!self::passes(($sequence[$callNumber - 1] ?? null) === $method)) {
            $this->fail("Expected call #$callNumber on the {$this->subject} to be $method().", $method, $sequence);
        }

        return $this;
    }

    /** Groups several assertions about one method. */
    public function checkMethod(string $method, callable $assertions): self
    {
        $assertions(new AssertMockMethod($this, $method));
        return $this;
    }

    // ---- Internals ---------------------------------------------------------------------------

    /** Counts the assertion and returns $condition. */
    private static function passes(bool $condition): bool
    {
        TestContext::countAssertions();

        return $condition;
    }

    private function failCallCount(string $method, string $expectation, int $actualCount): void
    {
        $this->fail("Expected {$this->subject}: $method() to be called $expectation, but it was called $actualCount time(s).", $expectation, $actualCount);
    }

    private function fail(string $message, mixed $expected, mixed $actual, ?Diff $diff = null): void
    {
        Assert::failWith(new AssertionFailure($message, $expected, $actual, $diff));
    }

    private static function shortName(string $class): string
    {
        $separator = strrpos($class, '\\');

        return $separator === false ? $class : substr($class, $separator + 1);
    }
}
