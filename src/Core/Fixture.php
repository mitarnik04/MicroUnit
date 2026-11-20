<?php

namespace MicroUnit\Core;

/** A named resource tests can ask for by parameter name, declared with Tester::fixture(). */
final readonly class Fixture
{
    /**
     * @param \Closure(mixed ...$fixtures): mixed $factory may itself ask for other fixtures by parameter name
     * @param (\Closure(mixed $value): void)|null $cleanup
     * @param bool $isShared one instance per suite instead of one per test
     */
    public function __construct(
        public string $name,
        public \Closure $factory,
        public ?\Closure $cleanup = null,
        public bool $isShared = false,
    ) {}
}
