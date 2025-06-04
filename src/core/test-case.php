<?php

class TestCase
{
    public function __construct(
        public string $testNameSuffix,
        public mixed $params
    ) {}
}
