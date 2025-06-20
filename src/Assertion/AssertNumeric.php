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

    public function isGreaterThan(int|float $expected): self
    {
        Assert::isGreaterThan($expected, $this->source);
        return $this;
    }

    public function isLessThan(int|float $expected): self
    {
        Assert::isLessThan($expected, $this->source);
        return $this;
    }

    public function isBetween(int|float $min, int|float $max, bool $inclusive = true): self
    {
        Assert::isBetween($min, $max, $this->source, $inclusive);
        return $this;
    }
}
