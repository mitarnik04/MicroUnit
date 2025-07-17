<?php

namespace MicroUnit\Mock;

class CallLog
{
    private array $callLog = [];
    private array $callSequence = [];

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
        $this->callSequence[] = $method;
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

    /** @return array<string> */
    public function getCallSequence(): array
    {
        return $this->callSequence;
    }
}
