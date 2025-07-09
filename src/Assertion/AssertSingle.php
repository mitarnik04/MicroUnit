<?php

namespace MicroUnit\Assertion;

class AssertSingle
{
    private function __construct(
        public readonly mixed $source
    ) {}

    public static function begin(mixed $source): self
    {
        return new AssertSingle($source);
    }

    public function equals(mixed $expected): self
    {
        Assert::equals($expected, $this->source);
        return $this;
    }

    public function notEquals(mixed $unexpected): self
    {
        Assert::notEquals($unexpected, $this->source);
        return $this;
    }

    public function exact(mixed $expected): self
    {
        Assert::exact($expected, $this->source);
        return $this;
    }

    public function notExact(mixed $unexpected): self
    {
        Assert::notExact($unexpected, $this->source);
        return $this;
    }

    public function instanceOf(string $expectedInstance): self
    {
        Assert::instanceOf($expectedInstance, $this->source);
        return $this;
    }

    public function isTrue(): self
    {
        Assert::isTrue($this->source);
        return $this;
    }

    public function isFalse(): self
    {
        Assert::isFalse($this->source);
        return $this;
    }

    public function isNull(): self
    {
        Assert::isNull($this->source);
        return $this;
    }

    public function notNull(): self
    {
        Assert::notNull($this->source);
        return $this;
    }
}
