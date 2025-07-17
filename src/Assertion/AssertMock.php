<?php

namespace MicroUnit\Assertion;

use MicroUnit\Exceptions\TestFailedException;
use MicroUnit\Helpers\Diff;
use MicroUnit\Helpers\ValueExporter;
use MicroUnit\Mock\CallLog;
use MicroUnit\Mock\MicroMock;

class AssertMock
{
    private CallLog $callLog;

    private function __construct(
        public readonly MicroMock $source
    ) {
        $this->callLog = $source->getCallLog();
    }

    public static function begin(MicroMock $source): self
    {
        return new AssertMock($source);
    }

    public function isCalledTimes(string $method, int $expectedCallCount): self
    {
        return $this->compareToActualCallCount(
            $method,
            $expectedCallCount,
            [0],
            "Expected Mock method $method to be called $expectedCallCount time(s)"
        );
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
        return $this->compareToActualCallCount(
            $method,
            $minCallCount,
            [0, 1],
            "Expected Mock method $method to be called at least $minCallCount time(s)"
        );
    }

    public function isCalledMoreThan(string $method, int $minCallCount): self
    {
        return $this->compareToActualCallCount(
            $method,
            $minCallCount,
            [1],
            "Expected Mock method $method to be called more than $minCallCount time(s)"
        );
    }

    public function isCalledAtMost(string $method, int $maxCallCount): self
    {
        return $this->compareToActualCallCount(
            $method,
            $maxCallCount,
            [-1, 0],
            "Expected Mock method $method to be called at most $maxCallCount time(s)"
        );
    }

    public function isCalledLessThan(string $method, int $maxCallCount): self
    {
        return $this->compareToActualCallCount(
            $method,
            $maxCallCount,
            [-1],
            "Expected Mock method $method to be called less than $maxCallCount time(s)"
        );
    }

    /** @param array<mixed> $expectedArgs */
    public function isCalledWith(string $method, array $expectedArgs, bool $showActualMethodCallsOnError = true): self
    {
        $allCallArgs = $this->callLog->getAllCallArgs($method);
        foreach ($allCallArgs as $callArgs) {
            if ($callArgs === $expectedArgs) {
                $matched = true;
                break;
            }
        }
        if (!$matched) {
            $message = "Expected method '$method' to be called with: " . ValueExporter::export($expectedArgs);
            if ($showActualMethodCallsOnError) {
                $message .= PHP_EOL .
                    'Actually called with: ' . ValueExporter::export($allCallArgs);
            }
            throw new TestFailedException($message);
        }

        return $this;
    }

    /** @param array<mixed> $expectedArgs */
    public function isCalledWithOnSpecificCall(string $method, array $expectedArgs, int $onCall): self
    {
        $callArgs = $this->callLog->getAllCallArgs($method)[$onCall - 1];
        if ($callArgs !== $expectedArgs) {
            $diff = Diff::generate(ValueExporter::export($expectedArgs), ValueExporter::export($callArgs));

            throw new TestFailedException("Method '$method' on call $onCall was not called with specified arguments" . PHP_EOL
                . ValueExporter::export($diff));
        }

        return $this;
    }

    public function isOnlyCalledWith(string $method, array $expectedArgs, bool $showActualMethodCallsOnError = true): self
    {
        $allCallArgs = $this->callLog->getAllCallArgs($method);
        $matched = true;
        foreach ($allCallArgs as $callArgs) {
            if ($callArgs !== $expectedArgs) {
                $matched = false;
                break;
            }
        }
        if (!$matched) {
            $message = "Expected method '$method' to only be called with: " . ValueExporter::export($expectedArgs);
            if ($showActualMethodCallsOnError) {
                $message .= PHP_EOL .
                    'Actually called with: ' . ValueExporter::export($allCallArgs);
            }
            throw new TestFailedException($message);
        }

        return $this;
    }

    /** @param callable(array $callArgs): bool $matcher */
    public function isOnlyCalledWithMatchingArgs(string $method, callable $matcher, bool $showActualMethodCallsOnError = true): self
    {
        $allCallArgs = $this->callLog->getAllCallArgs($method);
        $matched = true;
        foreach ($allCallArgs as $callArgs) {
            if (!$matcher($callArgs)) {
                $matched = false;
                break;
            }
        }
        if (!$matched) {
            $message = "Expected '$method' to only be called with arguments matching the callable conditions";
            if ($showActualMethodCallsOnError) {
                $message .= PHP_EOL .
                    'Actually called with: ' . ValueExporter::export($allCallArgs);
            }
            throw new TestFailedException($message);
        }

        return $this;
    }

    /** @param callable(array $callArgs): bool $matcher */
    public function isCalledWithMatchingOnSpecificCall(string $method, callable $matcher, int $onCall): self
    {
        $callArgs = $this->callLog->getAllCallArgs($method)[$onCall - 1];
        if (!$matcher($callArgs)) {
            throw new TestFailedException("Arguments passed to method '$method' on call $onCall did not match callable conditions" . PHP_EOL
                . 'Actual args: ' . ValueExporter::export($callArgs));
        }

        return $this;
    }


    public function isCalledOn(string $method, int $callNumber): self
    {
        $callSequence = $this->callLog->getCallSequence();
        $actualCalledMethod = $callSequence[$callNumber - 1];
        if ($actualCalledMethod !== $method) {
            throw new TestFailedException("Method '$method' was not called on call $callNumber.
Actual called method: '$actualCalledMethod'");
        }

        return $this;
    }

    /** @param callable(AssertMockMethod $assert): void $checkFunction */
    public function checkMethod(string $method, callable $assertMethod): self
    {
        $assertMethod(new AssertMockMethod($this, $method));
        return $this;
    }

    private function compareToActualCallCount(string $method, int $callCount, array $allowedSpaceShipResults, string $expectedMessage): self
    {
        $actualCallCount = $this->callLog->getCallCount($method);
        $comparisonResult = $actualCallCount <=> $callCount;

        if (!in_array($comparisonResult, $allowedSpaceShipResults)) {
            throw new TestFailedException($expectedMessage . PHP_EOL .
                "Actually called: $actualCallCount time(s)");
        }

        return $this;
    }
}
