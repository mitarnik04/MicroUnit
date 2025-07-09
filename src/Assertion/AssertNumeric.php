<?php

namespace MicroUnit\Assertion;

class AssertNumeric
{
    private function __construct(
        public readonly int|float $source
    ) {}

    public static function begin(int|float $source): self
    {
        return new AssertNumeric($source);
    }

    public function exact(int|float $expected): self
    {
        Assert::exact($expected, $this->source);
        return $this;
    }

    public function notExact(int|float $unexpected): self
    {
        Assert::notExact($unexpected, $this->source);
        return $this;
    }

    public function isGreaterThan(int|float $min): self
    {
        Assert::isGreaterThan($this->source, $min);
        return $this;
    }

    public function isLessThan(int|float $max): self
    {
        Assert::isLessThan($this->source, $max);
        return $this;
    }

    public function isBetween(int|float $min, int|float $max, bool $inclusive = true): self
    {
        Assert::isBetween($min, $max, $this->source, $inclusive);
        return $this;
    }
}
