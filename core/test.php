<?php

class Test
{
    /** @param callable(...$args): mixed $testCallable */
    public function __construct(
        public readonly string $name,
        public $testCallable,
        public mixed $args,
    ) {}
}
