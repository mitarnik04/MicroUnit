<?php

namespace MicroUnit\Helpers;

class DiffLine
{
    public function __construct(
        public readonly DiffLineType $type,
        public readonly string $line
    ) {}
}
