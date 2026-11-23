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

    /** The type, written the way PHP writes it: 'int', '?string', 'int|float', 'list', or a class name. */
    public function isType(string $expectedType, string $message = ''): self
    {
        Assert::isType($expectedType, $this->actual, $message);
        return $this;
    }

    /** Same contents, order ignored: lists compared as multisets, maps by key, recursively. */
    public function equivalent(mixed $expected, string $message = ''): self
    {
        Assert::equivalent($expected, $this->actual, $message);
        return $this;
    }

    /** An object with this property; any other value fails with its type. */
    public function hasProperty(string $property, string $message = ''): self
    {
        if (!is_object($this->actual)) {
            Assert::isType('object', $this->actual, $message);
            return $this;
        }
        Assert::hasProperty($property, $this->actual, $message);
        return $this;
    }

    /** Same moment in time, give or take $toleranceInSeconds; a value that is not a date fails with its type. */
    public function dateEquals(\DateTimeInterface|string $expected, int|float $toleranceInSeconds = 0, string $message = ''): self
    {
        if (!$this->actual instanceof \DateTimeInterface && !is_string($this->actual)) {
            Assert::isType('DateTimeInterface|string', $this->actual, $message);
            return $this;
        }
        Assert::dateEquals($expected, $this->actual, $toleranceInSeconds, $message);
        return $this;
    }

    /** Compares the value with the snapshot stored for this test, like Assert::matchesSnapshot(). */
    public function matchesSnapshot(?string $name = null, string $message = ''): self
    {
        Assert::matchesSnapshot($this->actual, $name, $message);
        return $this;
    }
}
