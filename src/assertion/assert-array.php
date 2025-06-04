<?php
require_once __DIR__ . '/assert.php';

class AssertArray
{
    private function __construct(
        public readonly array $source
    ) {}

    public static function begin(array $source)
    {
        return new AssertArray($source);
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
}
