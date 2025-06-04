<?php
require_once __DIR__ . '/assert.php';

class AssertSingle
{
    private function __construct(
        public readonly mixed $source
    ) {}

    public static function begin(mixed $source): AssertSingle
    {
        return new AssertSingle($source);
    }

    public function equals(mixed $expected): AssertSingle
    {
        Assert::equals($expected, $this->source);
        return $this;
    }

    public function notEquals(mixed $expected): AssertSingle
    {
        Assert::notEquals($expected, $this->source);
        return $this;
    }

    public function exact(mixed $expected): AssertSingle
    {
        Assert::exact($expected, $this->source);
        return $this;
    }

    public function notExact(mixed $expected): AssertSingle
    {
        Assert::notExact($expected, $this->source);
        return $this;
    }

    public function instanceOf($expectedInstance): AssertSingle
    {
        Assert::instanceOf($expectedInstance, $this->source);
        return $this;
    }
}
