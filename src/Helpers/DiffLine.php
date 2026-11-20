<?php

namespace MicroUnit\Helpers;

final readonly class DiffLine
{
    public function __construct(
        public DiffLineType $type,
        public string $line,
    ) {}
}
