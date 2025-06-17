<?php

namespace MicroUnit\Assertion;

use MicroUnit\Exceptions\TestFailedException;
use MicroUnit\Helpers\Diff;
use MicroUnit\Helpers\ValueExporter;
use MicroUnit\Mock\CallLog;
use MicroUnit\Mock\MicroMock;
use ValueError;

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
        $actualCallCount = $this->callLog->getCallCount($method);
        if ($actualCallCount !== $expectedCallCount) {
            throw new TestFailedException("Expected Mock method $method to be called $expectedCallCount time(s)" . PHP_EOL .
                "Actually called: $actualCallCount time(s)");
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
        $actualCallCount = $this->callLog->getCallCount($method);

        if ($actualCallCount < $minCallCount) {
            throw new TestFailedException("Expected Mock method $method to be called at least $minCallCount time(s)" . PHP_EOL .
                "Actually called: $actualCallCount time(s)");
        }

        return $this;
    }

    public function isCalledMoreThan(string $method, int $minCallCount): self
    {
        $actualCallCount = $this->callLog->getCallCount($method);

        if ($actualCallCount <= $minCallCount) {
            throw new TestFailedException("Expected Mock method $method to be called more than $minCallCount time(s)" . PHP_EOL .
                "Actually called: $actualCallCount time(s)");
        }

        return $this;
    }

    public function isCalledAtMost(string $method, int $maxCallCount)
    {
        $actualCallCount = $this->callLog->getCallCount($method);

        if ($actualCallCount > $maxCallCount) {
            throw new TestFailedException("Expected Mock method $method to be called at most $maxCallCount time(s)" . PHP_EOL .
                "Actually called: $actualCallCount time(s)");
        }

        return $this;
    }

    public function isCalledLessThan(string $method, int $maxCallCount)
    {
        $actualCallCount = $this->callLog->getCallCount($method);

        if ($actualCallCount >= $maxCallCount) {
            throw new TestFailedException("Expected Mock method $method to be called less than $maxCallCount time(s)" . PHP_EOL .
                "Actually called: $actualCallCount time(s)");
        }

        return $this;
    }

    public function isCalledWith(string $method, array $expectedArgs, bool $showActualMethodCallsOnError = false)
    {
        $allCallArgs = $this->callLog->getAllCallArgs($method);
        foreach ($allCallArgs as $callArgs) {
            if ($callArgs === $expectedArgs) {
                $matched = true;
                break;
            }
        }
        if (!$matched) {
            $message = "Expected {$method} to be called with " . ValueExporter::export($expectedArgs);
            if ($showActualMethodCallsOnError) {
                $message .= PHP_EOL .
                    'Actually called with: ' . ValueExporter::export($allCallArgs);
            }
            throw new TestFailedException($message);
        }
    }

    public function isCalledWithOnSpecificCall(string $method, array $expectedArgs, int $onCall)
    {
        $callArgs = $this->callLog->getAllCallArgs($method)[$onCall - 1];
        if ($callArgs !== $expectedArgs) {
            $diff = Diff::generate(ValueExporter::export($expectedArgs), ValueExporter::export($callArgs));

            throw new TestFailedException("Method $method on call $onCall was not called with specified arguments" . PHP_EOL
                . ValueExporter::export($diff));
        }
    }
}
