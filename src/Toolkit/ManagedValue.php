<?php

namespace MicroUnit\Toolkit;

/** @internal a value held by a ValueBundle */
final readonly class ManagedValue
{
    public function __construct(
        public mixed $value,
        public bool $isReadonly,
    ) {}
}
