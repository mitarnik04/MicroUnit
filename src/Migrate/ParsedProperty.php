<?php

namespace MicroUnit\Migrate;

final readonly class ParsedProperty
{
    public function __construct(
        public string $name,
        public ?string $type,
        public ?string $default,
        public bool $isStatic,
    ) {}
}
