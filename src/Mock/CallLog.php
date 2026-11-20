<?php

namespace MicroUnit\Mock;

/** Records which methods were called, how often, with which arguments and in which order. */
final class CallLog
{
    /** @var array<string, list<list<mixed>>> method => arguments of each call */
    private array $argumentsByMethod = [];

    /** @var list<string> */
    private array $callSequence = [];

    /** @param list<mixed> $args */
    public function addCall(string $method, array $args = []): void
    {
        $this->argumentsByMethod[$method][] = $args;
        $this->callSequence[] = $method;
    }

    public function getCallCount(string $method): int
    {
        return count($this->argumentsByMethod[$method] ?? []);
    }

    /** @return list<list<mixed>> */
    public function getAllCallArgs(string $method): array
    {
        return $this->argumentsByMethod[$method] ?? [];
    }

    /** @return list<mixed>|null arguments of the given call (1-based), null if there was no such call */
    public function getCallArgs(string $method, int $callNumber): ?array
    {
        return $this->argumentsByMethod[$method][$callNumber - 1] ?? null;
    }

    public function hasCalls(string $method): bool
    {
        return isset($this->argumentsByMethod[$method]);
    }

    /** @return list<string> method names in the order they were called */
    public function getCallSequence(): array
    {
        return $this->callSequence;
    }
}
