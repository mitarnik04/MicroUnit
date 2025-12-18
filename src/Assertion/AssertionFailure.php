<?php

namespace MicroUnit\Assertion;

use MicroUnit\Helpers\Diff;

final class AssertionFailure
{
    public function __construct(
        public readonly string $message = 'Unknown Assertion Failure',
        public readonly mixed $expected = null,
        public readonly mixed $actual = null,
        public readonly ?Diff $diff = null,
        public readonly array $metadata = []
    ) {}
}
