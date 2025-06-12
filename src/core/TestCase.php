<?php

namespace MicroUnit\Core;

class TestCase
{
    public function __construct(
        public string $testNameSuffix,
        public mixed $params
    ) {}
}
