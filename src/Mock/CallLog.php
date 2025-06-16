<?php

namespace MicroUnit\Mock;

class CallLog
{
    private array $callLog = [];

    public function addCall(string $method, array $callArgs = []): void
    {
        if (!isset($this->callLog[$method])) {
            $this->callLog[$method] = [
                'callLog' => 0,
                'argLog' => [],
            ];
        }

        $this->callLog[$method]['argLog'][] = $callArgs;
        $this->callLog[$method]['callLog']++;
    }

    public function getCallCount(string $method): int
    {
        return $this->callLog[$method]['callLog'] ?? 0;
    }

    public function getAllCallArgs(string $method): array
    {
        return $this->callLog[$method]['argLog'] ?? [];
    }

    public function hasCalls(string $method): bool
    {
        return !empty($this->callLog[$method]['callLog']);
    }
}
