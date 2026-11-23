<?php

namespace MicroUnit\Assertion;

/** Fluent assertions for arrays. */
final readonly class AssertArray
{
    /** @param array<mixed> $actual */
    private function __construct(
        private array $actual,
    ) {}

    /** @param array<mixed> $actual */
    public static function begin(array $actual): self
    {
        return new self($actual);
    }

    /** @param array<mixed> $expected */
    public function equals(array $expected, string $message = ''): self
    {
        Assert::equals($expected, $this->actual, $message);
        return $this;
    }

    /** @param array<mixed> $unexpected */
    public function notEquals(array $unexpected, string $message = ''): self
    {
        Assert::notEquals($unexpected, $this->actual, $message);
        return $this;
    }

    /** @param array<mixed> $expected */
    public function exact(array $expected, string $message = ''): self
    {
        Assert::exact($expected, $this->actual, $message);
        return $this;
    }

    /** @param array<mixed> $unexpected */
    public function notExact(array $unexpected, string $message = ''): self
    {
        Assert::notExact($unexpected, $this->actual, $message);
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

    public function contains(mixed $needle, bool $strict = true, string $message = ''): self
    {
        Assert::contains($needle, $this->actual, $strict, $message);
        return $this;
    }

    public function notContains(mixed $needle, bool $strict = true, string $message = ''): self
    {
        Assert::notContains($needle, $this->actual, $strict, $message);
        return $this;
    }

    public function countEquals(int $expected, string $message = ''): self
    {
        Assert::countEquals($expected, $this->actual, $message);
        return $this;
    }

    public function hasKey(int|string $key, string $message = ''): self
    {
        Assert::hasKey($key, $this->actual, $message);
        return $this;
    }

    public function notHasKey(int|string $key, string $message = ''): self
    {
        Assert::notHasKey($key, $this->actual, $message);
        return $this;
    }

    /** @param list<int|string> $expectedKeys */
    public function keysEqual(array $expectedKeys, string $message = ''): self
    {
        Assert::keysEqual($expectedKeys, $this->actual, $message);
        return $this;
    }

    /** @param array<mixed> $allowedValues */
    public function containsOnly(array $allowedValues, string $message = ''): self
    {
        Assert::containsOnly($allowedValues, $this->actual, $message);
        return $this;
    }

    /**
     * Same contents, order ignored: lists compared as multisets, maps by key, recursively.
     *
     * @param array<mixed> $expected
     */
    public function equivalent(array $expected, string $message = ''): self
    {
        Assert::equivalent($expected, $this->actual, $message);
        return $this;
    }

    /**
     * Every element of $expected is in the array, in any order; other elements are allowed.
     *
     * @param array<mixed> $expected
     */
    public function containsAll(array $expected, bool $strict = true, string $message = ''): self
    {
        Assert::containsAll($expected, $this->actual, $strict, $message);
        return $this;
    }

    /**
     * At least the keys of $subset with equal values, recursively; everything else is ignored.
     *
     * @param array<mixed>|object $subset
     */
    public function containsSubset(array|object $subset, string $message = ''): self
    {
        Assert::containsSubset($subset, $this->actual, $message);
        return $this;
    }

    /** Keys 0, 1, 2, ... in order. */
    public function isList(string $message = ''): self
    {
        Assert::isList($this->actual, $message);
        return $this;
    }

    /** No element appears twice (strict comparison). */
    public function isUnique(string $message = ''): self
    {
        Assert::isUnique($this->actual, $message);
        return $this;
    }

    /**
     * In ascending (or descending) order, compared with <=>, optionally by a key.
     *
     * @param (callable(mixed $element): mixed)|null $by
     */
    public function isSorted(?callable $by = null, bool $descending = false, string $message = ''): self
    {
        Assert::isSorted($this->actual, $by, $descending, $message);
        return $this;
    }

    /**
     * Every element passes $check, which asserts (or returns false to fail); all failing elements are reported.
     *
     * @param callable(mixed $item, int|string $key): mixed $check
     */
    public function each(callable $check, string $message = ''): self
    {
        Assert::each($this->actual, $check, $message);
        return $this;
    }

    /** @param array<mixed>|\Countable $expected */
    public function sameSize(array|\Countable $expected, string $message = ''): self
    {
        Assert::sameSize($expected, $this->actual, $message);
        return $this;
    }

    /** Compares the array with the snapshot stored for this test, like Assert::matchesSnapshot(). */
    public function matchesSnapshot(?string $name = null, string $message = ''): self
    {
        Assert::matchesSnapshot($this->actual, $name, $message);
        return $this;
    }
}
