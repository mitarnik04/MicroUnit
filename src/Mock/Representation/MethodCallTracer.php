<?php

namespace MicroUnit\Mock\Representation;

trait MethodCallTracer
{
    private array $callHistory = [];

    protected function traceCall(string $methodName, array $args): void
    {
        $this->callHistory[$methodName][] = $args;
    }

    public function getCallHistory(): array
    {
        return $this->callHistory;
    }

    public function getCallCount(string $methodName): int
    {
        return isset($this->callHistory[$methodName])
            ? count($this->callHistory[$methodName])
            : 0;
    }

    public function getCalls(string $methodName): array
    {
        return $this->callHistory[$methodName] ?? [];
    }

    public function resetCallHistory(): void
    {
        $this->callHistory = [];
    }
}
