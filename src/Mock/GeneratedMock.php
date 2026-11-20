<?php

namespace MicroUnit\Mock;

/** A generated mock class plus the value each intercepted method returns when nothing is configured. */
final readonly class GeneratedMock
{
    /**
     * @param class-string $className
     * @param array<string, \Closure(object): mixed> $defaultReturns method => factory receiving the instance
     */
    public function __construct(
        public string $className,
        public array $defaultReturns,
    ) {}
}
