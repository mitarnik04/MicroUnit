<?php

namespace MicroUnit\Assertion;

/** Fluent assertions for arrays. */
final readonly class AssertArray
{
    /** @param array<mixed> $actual */
    private function __construct(
        private array $actual,
    ) {}

    /** @param array<mixed> $actual */
    public static function begin(array $actual): self
    {
        return new self($actual);
    }

    /** @param array<mixed> $expected */
    public function equals(array $expected, string $message = ''): self
    {
        Assert::equals($expected, $this->actual, $message);
        return $this;
    }

    /** @param array<mixed> $unexpected */
    public function notEquals(array $unexpected, string $message = ''): self
    {
        Assert::notEquals($unexpected, $this->actual, $message);
        return $this;
    }

    /** @param array<mixed> $expected */
    public function exact(array $expected, string $message = ''): self
    {
        Assert::exact($expected, $this->actual, $message);
        return $this;
    }

    /** @param array<mixed> $unexpected */
    public function notExact(array $unexpected, string $message = ''): self
    {
        Assert::notExact($unexpected, $this->actual, $message);
        return $this;
    }

    public function empty(string $message = ''): self
    {
        Assert::empty($this->actual, $message);
        return $this;
    }

    public function notEmpty(string $message = ''): self
    {
        Assert::notEmpty($this->actual, $message);
        return $this;
    }

    public function contains(mixed $needle, bool $strict = true, string $message = ''): self
    {
        Assert::contains($needle, $this->actual, $strict, $message);
        return $this;
    }

    public function notContains(mixed $needle, bool $strict = true, string $message = ''): self
    {
        Assert::notContains($needle, $this->actual, $strict, $message);
        return $this;
    }

    public function countEquals(int $expected, string $message = ''): self
    {
        Assert::countEquals($expected, $this->actual, $message);
        return $this;
    }

    public function hasKey(int|string $key, string $message = ''): self
    {
        Assert::hasKey($key, $this->actual, $message);
        return $this;
    }

    public function notHasKey(int|string $key, string $message = ''): self
    {
        Assert::notHasKey($key, $this->actual, $message);
        return $this;
    }

    /** @param list<int|string> $expectedKeys */
    public function keysEqual(array $expectedKeys, string $message = ''): self
    {
        Assert::keysEqual($expectedKeys, $this->actual, $message);
        return $this;
    }

    /** @param array<mixed> $allowedValues */
    public function containsOnly(array $allowedValues, string $message = ''): self
    {
        Assert::containsOnly($allowedValues, $this->actual, $message);
        return $this;
    }
}
