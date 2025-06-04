<?php
require_once __DIR__ . '/assert.php';

class AssertArray
{
    private function __construct(
        public readonly array $source
    ) {}

    public static function begin(array $source): AssertArray
    {
        return new AssertArray($source);
    }

    public function equals(array $expected): AssertArray
    {
        Assert::equals($expected, $this->source);
        return $this;
    }

    public function notEquals(array $expected): AssertArray
    {
        Assert::notEquals($expected, $this->source);
        return $this;
    }

    public function exact(array $expected): AssertArray
    {
        Assert::exact($expected, $this->source);
        return $this;
    }

    public function notExact(array $expected): AssertArray
    {
        Assert::notExact($expected, $this->source);
        return $this;
    }


    public function empty(): AssertArray
    {
        Assert::empty($this->source);
        return $this;
    }

    public function notEmpty(): AssertArray
    {
        Assert::notEmpty($this->source);
        return $this;
    }

    public function contains(mixed $element, bool $shouldUseStrict = true): AssertArray
    {
        Assert::contains($element, $this->source, $shouldUseStrict);
        return $this;
    }


    public function countEquals(int $expected): AssertArray
    {
        Assert::countEquals($expected, $this->source);
        return $this;
    }

    public function hasKey(mixed $key): AssertArray
    {
        Assert::hasKey($key, $this->source);
        return $this;
    }

    public function notHasKey(mixed $key): AssertArray
    {
        Assert::notHasKey($key, $this->source);
        return $this;
    }

    public function keysEqual(array $expectedKeys): AssertArray
    {
        Assert::keysEqual($expectedKeys, $this->source);
        return $this;
    }

    public function containsOnly(array $allowedValues): AssertArray
    {
        Assert::containsOnly($allowedValues, $this->source);
        return $this;
    }
}
