<?php

namespace MicroUnit\Assertion;

/** Fluent assertions for integers and floats. */
final readonly class AssertNumeric
{
    private function __construct(
        private int|float $actual,
    ) {}

    public static function begin(int|float $actual): self
    {
        return new self($actual);
    }

    public function equals(int|float $expected, string $message = ''): self
    {
        Assert::equals($expected, $this->actual, $message);
        return $this;
    }

    public function exact(int|float $expected, string $message = ''): self
    {
        Assert::exact($expected, $this->actual, $message);
        return $this;
    }

    public function notExact(int|float $unexpected, string $message = ''): self
    {
        Assert::notExact($unexpected, $this->actual, $message);
        return $this;
    }

    public function equalsWithDelta(int|float $expected, int|float $delta, string $message = ''): self
    {
        Assert::equalsWithDelta($expected, $this->actual, $delta, $message);
        return $this;
    }

    public function isGreaterThan(int|float $minimum, string $message = ''): self
    {
        Assert::isGreaterThan($minimum, $this->actual, $message);
        return $this;
    }

    public function isGreaterThanOrEqual(int|float $minimum, string $message = ''): self
    {
        Assert::isGreaterThanOrEqual($minimum, $this->actual, $message);
        return $this;
    }

    public function isLessThan(int|float $maximum, string $message = ''): self
    {
        Assert::isLessThan($maximum, $this->actual, $message);
        return $this;
    }

    public function isLessThanOrEqual(int|float $maximum, string $message = ''): self
    {
        Assert::isLessThanOrEqual($maximum, $this->actual, $message);
        return $this;
    }

    public function isBetween(int|float $minimum, int|float $maximum, bool $inclusive = true, string $message = ''): self
    {
        Assert::isBetween($minimum, $maximum, $this->actual, $inclusive, $message);
        return $this;
    }

    public function notEquals(int|float $unexpected, string $message = ''): self
    {
        Assert::notEquals($unexpected, $this->actual, $message);
        return $this;
    }

    /** Relative tolerance: equalsWithinPercent(100, 5) accepts 95 to 105. */
    public function equalsWithinPercent(int|float $expected, int|float $percent, string $message = ''): self
    {
        Assert::equalsWithinPercent($expected, $this->actual, $percent, $message);
        return $this;
    }

    public function isNan(string $message = ''): self
    {
        Assert::isNan($this->actual, $message);
        return $this;
    }

    /** Not NAN and not ±INF. */
    public function isFinite(string $message = ''): self
    {
        Assert::isFinite($this->actual, $message);
        return $this;
    }
}
