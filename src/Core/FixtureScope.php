<?php

namespace MicroUnit\Core;

/**
 * Holds the fixture instances of one lifetime (a single test, or a whole suite) and cleans them
 * up in reverse creation order, so a fixture is torn down before the fixtures it was built from.
 */
final class FixtureScope
{
    /** @var array<string, mixed> fixture name => instance */
    private array $instances = [];

    /** @var list<string> creation order */
    private array $creationOrder = [];

    public function has(string $name): bool
    {
        return array_key_exists($name, $this->instances);
    }

    public function get(string $name): mixed
    {
        return $this->instances[$name];
    }

    /** A new value for an existing fixture (setUp took it by reference and assigned it). */
    public function replace(string $name, mixed $instance): void
    {
        $this->instances[$name] = $instance;
    }

    public function add(string $name, mixed $instance): void
    {
        $this->instances[$name] = $instance;
        $this->creationOrder[] = $name;
    }

    /**
     * Runs every cleanup, even when one of them fails; the first failure is rethrown afterwards.
     *
     * @param array<string, Fixture> $fixtures
     */
    public function cleanUp(array $fixtures): void
    {
        $firstFailure = null;
        foreach (array_reverse($this->creationOrder) as $name) {
            $cleanup = $fixtures[$name]->cleanup ?? null;
            try {
                $cleanup?->__invoke($this->instances[$name]);
            } catch (\Throwable $throwable) {
                $firstFailure ??= new \RuntimeException("Cleanup of fixture '$name' failed: " . $throwable->getMessage(), 0, $throwable);
            }
        }
        $this->instances = [];
        $this->creationOrder = [];

        if ($firstFailure !== null) {
            throw $firstFailure;
        }
    }
}
