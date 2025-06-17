<?php

namespace MicroUnit\Assertion;

class AssertNumeric
{
    private function __construct(
        public readonly int|float $source
    ) {}

    public static function begin(int|float $source): AssertNumeric
    {
        return new AssertNumeric($source);
    }

    public function isGreaterThan(int|float $expected): AssertNumeric
    {
        Assert::isGreaterThan($expected, $this->source);
        return $this;
    }

    public function isLessThan(int|float $expected): AssertNumeric
    {
        Assert::isLessThan($expected, $this->source);
        return $this;
    }

    public function isBetween(int|float $min, int|float $max, bool $inclusive = true): AssertNumeric
    {
        Assert::isBetween($min, $max, $this->source, $inclusive);
        return $this;
    }
}
