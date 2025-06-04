<?php
require_once __DIR__ . '/assert.php';

class AssertSingle
{
    private function __construct(
        public readonly mixed $source
    ) {}

    public static function begin(mixed $source)
    {
        return new AssertSingle($source);
    }

    public function instanceOf($expectedInstance)
    {
        Assert::instanceOf($expectedInstance, $this->source);
    }
}
