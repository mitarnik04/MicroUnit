<?php

namespace MicroUnit\Assertion;

/** Assertions scoped to one method of a mock; obtained via AssertMock::checkMethod(). */
final readonly class AssertMockMethod
{
    public function __construct(
        private AssertMock $assertMock,
        private string $method,
    ) {}

    public function isCalled(): self
    {
        $this->assertMock->isCalled($this->method);
        return $this;
    }

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

    public function isCalledMoreThan(int $callCount): self
    {
        $this->assertMock->isCalledMoreThan($this->method, $callCount);
        return $this;
    }

    public function isCalledAtMost(int $maxCallCount): self
    {
        $this->assertMock->isCalledAtMost($this->method, $maxCallCount);
        return $this;
    }

    public function isCalledLessThan(int $callCount): self
    {
        $this->assertMock->isCalledLessThan($this->method, $callCount);
        return $this;
    }

    public function isCalledWith(array $expectedArgs): self
    {
        $this->assertMock->isCalledWith($this->method, $expectedArgs);
        return $this;
    }

    public function isCalledWithOnSpecificCall(array $expectedArgs, int $callNumber): self
    {
        $this->assertMock->isCalledWithOnSpecificCall($this->method, $expectedArgs, $callNumber);
        return $this;
    }

    public function isOnlyCalledWith(array $expectedArgs): self
    {
        $this->assertMock->isOnlyCalledWith($this->method, $expectedArgs);
        return $this;
    }

    /** @param callable(list<mixed> $args): bool $matcher */
    public function isOnlyCalledWithMatchingArgs(callable $matcher): self
    {
        $this->assertMock->isOnlyCalledWithMatchingArgs($this->method, $matcher);
        return $this;
    }

    /** @param callable(list<mixed>|null $args): bool $matcher */
    public function isCalledWithMatchingOnSpecificCall(callable $matcher, int $callNumber): self
    {
        $this->assertMock->isCalledWithMatchingOnSpecificCall($this->method, $matcher, $callNumber);
        return $this;
    }

    public function isCalledOn(int $callNumber): self
    {
        $this->assertMock->isCalledOn($this->method, $callNumber);
        return $this;
    }
}
