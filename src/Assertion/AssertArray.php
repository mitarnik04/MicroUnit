<?php

namespace MicroUnit\Assertion;

class AssertArray
{
    private function __construct(
        public readonly array $source
    ) {}

    public static function begin(array $source): self
    {
        return new AssertArray($source);
    }

    public function equals(array $expected): self
    {
        Assert::equals($expected, $this->source);
        return $this;
    }

    public function notEquals(array $unexpected): self
    {
        Assert::notEquals($unexpected, $this->source);
        return $this;
    }

    public function exact(array $expected): self
    {
        Assert::exact($expected, $this->source);
        return $this;
    }

    public function notExact(array $unexpected): self
    {
        Assert::notExact($unexpected, $this->source);
        return $this;
    }


    public function empty(): self
    {
        Assert::empty($this->source);
        return $this;
    }

    public function notEmpty(): self
    {
        Assert::notEmpty($this->source);
        return $this;
    }

    public function contains(mixed $element, bool $shouldUseStrict = true): self
    {
        Assert::contains($element, $this->source, $shouldUseStrict);
        return $this;
    }


    public function countEquals(int $expected): self
    {
        Assert::countEquals($expected, $this->source);
        return $this;
    }

    public function hasKey(mixed $key): self
    {
        Assert::hasKey($key, $this->source);
        return $this;
    }

    public function notHasKey(mixed $key): self
    {
        Assert::notHasKey($key, $this->source);
        return $this;
    }

    public function keysEqual(array $expectedKeys): self
    {
        Assert::keysEqual($expectedKeys, $this->source);
        return $this;
    }

    public function containsOnly(array $allowedValues): self
    {
        Assert::containsOnly($allowedValues, $this->source);
        return $this;
    }
}
