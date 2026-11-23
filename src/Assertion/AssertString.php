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

    public function empty(string $message = ''): self
    {
        Assert::empty($this->actual, $message);
        return $this;
    }

    public function notEmpty(string $message = ''): self
    {
        Assert::notEmpty($this->actual, $message);
        return $this;
    }

    /** Strings compare strictly either way, so this is the same check as equals(). */
    public function exact(string $expected, string $message = ''): self
    {
        Assert::exact($expected, $this->actual, $message);
        return $this;
    }

    /** Strict, like equals(): "1.0" and "1" are different strings. */
    public function notEquals(string $unexpected, string $message = ''): self
    {
        Assert::notExact($unexpected, $this->actual, $message);
        return $this;
    }

    public function notExact(string $unexpected, string $message = ''): self
    {
        Assert::notExact($unexpected, $this->actual, $message);
        return $this;
    }

    public function equalsIgnoringCase(string $expected, string $message = ''): self
    {
        Assert::equalsIgnoringCase($expected, $this->actual, $message);
        return $this;
    }

    /** Equal after trimming and collapsing every run of whitespace to one space. */
    public function equalsIgnoringWhitespace(string $expected, string $message = ''): self
    {
        Assert::equalsIgnoringWhitespace($expected, $this->actual, $message);
        return $this;
    }

    public function containsIgnoringCase(string $needle, string $message = ''): self
    {
        Assert::stringContainsIgnoringCase($needle, $this->actual, $message);
        return $this;
    }

    /** Matches a format with placeholders such as %s and %d, like Assert::matchesFormat(). */
    public function matchesFormat(string $format, string $message = ''): self
    {
        Assert::matchesFormat($format, $this->actual, $message);
        return $this;
    }

    public function isJson(string $message = ''): self
    {
        Assert::isJson($this->actual, $message);
        return $this;
    }

    /** Same JSON document, key order and formatting ignored; $expected is a JSON string or the value it decodes to. */
    public function jsonEquals(mixed $expected, string $message = ''): self
    {
        Assert::jsonEquals($expected, $this->actual, $message);
        return $this;
    }

    /** Compares the string with the snapshot stored for this test, like Assert::matchesSnapshot(). */
    public function matchesSnapshot(?string $name = null, string $message = ''): self
    {
        Assert::matchesSnapshot($this->actual, $name, $message);
        return $this;
    }
}
