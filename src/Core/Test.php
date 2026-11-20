<?php

namespace MicroUnit\Core;

/** A registered test: what to run and where it was defined. */
final readonly class Test
{
    /** @param list<mixed> $args */
    public function __construct(
        public string $id,
        public string $suite,
        public string $name,
        public \Closure $callable,
        public array $args = [],
        public ?string $file = null,
        public ?int $line = null,
        public ?string $group = null,
    ) {}
}
