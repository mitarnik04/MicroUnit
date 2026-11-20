<?php

namespace MicroUnit\Core;

/**
 * Builds the argument list for a test (or fixture factory): parameters named like a fixture get
 * that fixture, all other parameters are filled from the positional values in order.
 * Only used for suites that declare fixtures; without any, the runner calls hooks and tests directly.
 */
final class FixtureResolver
{
    /**
     * Parameter facts per callable, read by reflection once instead of on every call: tests,
     * hooks and factories are the same closures for every test of a suite. Plain values only,
     * so an entry never keeps its closure (and whatever it captured) alive.
     *
     * @var \WeakMap<\Closure, list<array{name: string, isVariadic: bool, isByReference: bool, hasDefault: bool}>>|null
     */
    private static ?\WeakMap $parameters = null;

    /** @var list<string> fixtures currently being created (cycle detection) */
    private array $creating = [];

    /** @param array<string, Fixture> $fixtures */
    public function __construct(
        private readonly array $fixtures,
        private readonly FixtureScope $testScope,
        private readonly FixtureScope $suiteScope,
    ) {}

    /**
     * @param list<mixed> $positional
     * @return list<mixed>
     */
    public function argumentsFor(\Closure $callable, array $positional): array
    {
        $arguments = [];
        foreach (self::parametersOf($callable) as $position => $parameter) {
            if (isset($this->fixtures[$parameter['name']])) {
                $arguments[] = $this->fixture($parameter['name']);
            } elseif ($parameter['isVariadic']) {
                array_push($arguments, ...$positional);
                $positional = [];
            } elseif ($positional !== []) {
                $arguments[] = array_shift($positional);
            } elseif ($parameter['hasDefault']) {
                // Evaluated per call: a default like `new Clock()` must not be shared between tests.
                $arguments[] = new \ReflectionParameter($callable, $position)->getDefaultValue();
            } else {
                break; // let PHP report the missing argument
            }
        }

        return [...$arguments, ...$positional];
    }

    /**
     * Calls $callable with its fixtures and positional values. Fixture parameters taken by
     * reference (&$cart) can be assigned: the fixture then has the new value from here on.
     *
     * @param list<mixed> $positional
     */
    public function call(\Closure $callable, array $positional): mixed
    {
        $arguments = $this->argumentsFor($callable, $positional);
        $result = $callable(...$arguments);

        foreach (self::parametersOf($callable) as $index => $parameter) {
            $name = $parameter['name'];
            if ($parameter['isByReference'] && isset($this->fixtures[$name]) && array_key_exists($index, $arguments)) {
                ($this->fixtures[$name]->isShared ? $this->suiteScope : $this->testScope)->replace($name, $arguments[$index]);
            }
        }

        return $result;
    }

    private function fixture(string $name): mixed
    {
        $fixture = $this->fixtures[$name];
        if (!$fixture->isShared && $this->creating !== []) {
            $this->assertNoSharedFixtureIsBeingCreated($name);
        }

        $scope = $fixture->isShared ? $this->suiteScope : $this->testScope;
        if ($scope->has($name)) {
            return $scope->get($name);
        }

        if (in_array($name, $this->creating, true)) {
            throw new \LogicException('Fixtures depend on each other in a circle: ' . implode(' → ', [...$this->creating, $name]));
        }

        $this->creating[] = $name;
        try {
            $instance = ($fixture->factory)(...$this->argumentsFor($fixture->factory, []));
        } finally {
            array_pop($this->creating);
        }
        $scope->add($name, $instance);

        return $instance;
    }

    /** A shared fixture outlives the test, so it would keep a per-test fixture after its cleanup. */
    private function assertNoSharedFixtureIsBeingCreated(string $perTestFixture): void
    {
        $shared = array_find($this->creating, fn(string $name): bool => $this->fixtures[$name]->isShared);
        if ($shared !== null) {
            throw new \LogicException(
                "The shared fixture '$shared' cannot use the per-test fixture '$perTestFixture': it would keep the "
                . "instance of the first test after its cleanup. Make '$perTestFixture' shared too, or '$shared' per test.",
            );
        }
    }

    /** @return list<array{name: string, isVariadic: bool, isByReference: bool, hasDefault: bool}> */
    private static function parametersOf(\Closure $callable): array
    {
        $cache = self::$parameters ??= new \WeakMap();

        return $cache[$callable] ??= array_map(static fn(\ReflectionParameter $parameter): array => [
            'name' => $parameter->name,
            'isVariadic' => $parameter->isVariadic(),
            'isByReference' => $parameter->isPassedByReference(),
            'hasDefault' => $parameter->isDefaultValueAvailable(),
        ], new \ReflectionFunction($callable)->getParameters());
    }
}
