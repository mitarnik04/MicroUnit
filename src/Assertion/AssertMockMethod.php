<?php

namespace MicroUnit\Assertion;

class AssertMockMethod
{
    public function __construct(
        private readonly AssertMock $assertMock,
        private readonly string $method
    ) {}

    public function isCalledTimes(int $expectedCallCount): self
    {
        $this->assertMock->isCalledTimes($this->method, $expectedCallCount);
        return $this;
    }

    public function isCalledOnce(): self
    {
        $this->assertMock->isCalledOnce($this->method);
        return $this;
    }


    public function isNotCalled(): self
    {
        $this->assertMock->isNotCalled($this->method);
        return $this;
    }

    public function isCalledAtLeast(int $minCallCount): self
    {
        $this->assertMock->isCalledAtLeast($this->method, $minCallCount);
        return $this;
    }

    public function isCalledMoreThan(int $minCallCount): self
    {
        $this->assertMock->isCalledMoreThan($this->method, $minCallCount);
        return $this;
    }

    public function isCalledAtMost(int $maxCallCount): self
    {
        $this->assertMock->isCalledAtMost($this->method, $maxCallCount);
        return $this;
    }


    public function isCalledLessThan(int $maxCallCount): self
    {
        $this->assertMock->isCalledLessThan($this->method, $maxCallCount);
        return $this;
    }


    public function isCalledWith(array $expectedArgs, bool $showActualMethodCallsOnError = false): self
    {
        $this->assertMock->isCalledWith($this->method, $expectedArgs, $showActualMethodCallsOnError);
        return $this;
    }

    public function isCalledWithOnSpecificCall(array $expectedArgs, int $onCall): self
    {
        $this->assertMock->isCalledWithOnSpecificCall($this->method, $expectedArgs, $onCall);
        return $this;
    }
}
