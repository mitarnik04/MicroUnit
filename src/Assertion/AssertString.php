<?php

namespace MicroUnit\Assertion;

/** Fluent assertions for strings. */
final readonly class AssertString
{
    private function __construct(
        private string $actual,
    ) {}

    public static function begin(string $actual): self
    {
        return new self($actual);
    }

    public function equals(string $expected, string $message = ''): self
    {
        Assert::exact($expected, $this->actual, $message);
        return $this;
    }

    public function contains(string $needle, string $message = ''): self
    {
        Assert::stringContains($needle, $this->actual, $message);
        return $this;
    }

    public function notContains(string $needle, string $message = ''): self
    {
        Assert::stringNotContains($needle, $this->actual, $message);
        return $this;
    }

    public function startsWith(string $prefix, string $message = ''): self
    {
        Assert::stringStartsWith($prefix, $this->actual, $message);
        return $this;
    }

    public function endsWith(string $suffix, string $message = ''): self
    {
        Assert::stringEndsWith($suffix, $this->actual, $message);
        return $this;
    }

    public function matchesRegex(string $pattern, string $message = ''): self
    {
        Assert::matchesRegex($pattern, $this->actual, $message);
        return $this;
    }

    /** Length in UTF-8 characters (works without the mbstring extension). */
    public function lengthEquals(int $expected, string $message = ''): self
    {
        $length = preg_match_all('/./su', $this->actual);
        Assert::exact($expected, $length, $message === '' ? 'Failed asserting the string length (in characters).' : $message);
        return $this;
    }

    public function isEmpty(string $message = ''): self
    {
        Assert::empty($this->actual, $message);
        return $this;
    }

    public function notEmpty(string $message = ''): self
    {
        Assert::notEmpty($this->actual, $message);
        return $this;
    }
}
