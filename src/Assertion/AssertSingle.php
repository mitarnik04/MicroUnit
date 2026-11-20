<?php

namespace MicroUnit\Assertion;

/** Fluent assertions for a single value of any type. */
final readonly class AssertSingle
{
    private function __construct(
        private mixed $actual,
    ) {}

    public static function begin(mixed $actual): self
    {
        return new self($actual);
    }

    public function equals(mixed $expected, string $message = ''): self
    {
        Assert::equals($expected, $this->actual, $message);
        return $this;
    }

    public function notEquals(mixed $unexpected, string $message = ''): self
    {
        Assert::notEquals($unexpected, $this->actual, $message);
        return $this;
    }

    public function exact(mixed $expected, string $message = ''): self
    {
        Assert::exact($expected, $this->actual, $message);
        return $this;
    }

    public function notExact(mixed $unexpected, string $message = ''): self
    {
        Assert::notExact($unexpected, $this->actual, $message);
        return $this;
    }

    /** @param class-string $expectedClass */
    public function instanceOf(string $expectedClass, string $message = ''): self
    {
        Assert::instanceOf($expectedClass, $this->actual, $message);
        return $this;
    }

    public function isTrue(string $message = ''): self
    {
        Assert::isTrue($this->actual, $message);
        return $this;
    }

    public function isFalse(string $message = ''): self
    {
        Assert::isFalse($this->actual, $message);
        return $this;
    }

    public function isNull(string $message = ''): self
    {
        Assert::isNull($this->actual, $message);
        return $this;
    }

    public function notNull(string $message = ''): self
    {
        Assert::notNull($this->actual, $message);
        return $this;
    }
}
