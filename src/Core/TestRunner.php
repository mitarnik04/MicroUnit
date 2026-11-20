<?php

namespace MicroUnit\Core;

use MicroUnit\Exceptions\TestSkippedException;

/** Executes a single test: lifecycle hooks, setUp, fixtures, the test itself, tearDown and cleanup. */
final readonly class TestRunner
{
    /** @param list<ITestLifecycle> $lifecycles */
    public function __construct(
        private array $lifecycles = [],
    ) {}

    public function run(Test $test, SuiteContext $suite): TestResult
    {
        $outputLevel = ob_get_level();
        ob_start();
        TestContext::enter($test);

        $tester = $suite->tester;
        $fixtureDefinitions = $tester->getFixtures();
        // Suites without fixtures (most of them) skip fixture resolution entirely: no per-test work.
        $testFixtures = $fixtureDefinitions === [] ? null : new FixtureScope();
        $fixtures = $testFixtures === null ? null : new FixtureResolver($fixtureDefinitions, $testFixtures, $suite->sharedFixtures);
        $setUp = $tester->getSetUp();
        $setUpResult = null;
        $returnValue = null;
        $throwable = null;
        $startedAt = hrtime(true);
        $finishedAt = $startedAt;

        try {
            foreach ($this->lifecycles as $lifecycle) {
                $lifecycle->beforeTest($test);
            }
            // setUp may ask for fixtures by name, and gets the same instances as the test.
            $setUpResult = $setUp === null ? null : self::call($fixtures, $setUp, self::valueAsArguments($suite->sharedValue));
            $firstArgument = $setUp === null ? $suite->sharedValue : $setUpResult;
            $arguments = [...self::valueAsArguments($firstArgument), ...$test->args];
            if ($fixtures !== null) {
                $arguments = $fixtures->argumentsFor($test->callable, $arguments);
            }

            $startedAt = hrtime(true);
            $returnValue = ($test->callable)(...$arguments);
        } catch (\Throwable $caught) {
            $throwable = $caught;
        } finally {
            $finishedAt = hrtime(true);
        }

        $timeInSeconds = ($finishedAt - $startedAt) / 1e9;
        $result = match (true) {
            $throwable === null => TestResult::passed($test, $timeInSeconds, $returnValue),
            $throwable instanceof TestSkippedException => TestResult::skipped($test, $throwable->getMessage(), $timeInSeconds),
            default => TestResult::failed($test, $timeInSeconds, $throwable),
        };

        // Cleanup steps: if one throws, an otherwise passing test fails; an existing failure stays the reported cause.
        $tearDown = $tester->getTearDown();
        if ($tearDown !== null) {
            try {
                self::call($fixtures, $tearDown, [$result, $setUpResult]);
            } catch (\Throwable $caught) {
                $result = self::afterFailedCleanup($test, $result, $caught);
            }
        }
        if ($testFixtures !== null) {
            try {
                $testFixtures->cleanUp($fixtureDefinitions);
            } catch (\Throwable $caught) {
                $result = self::afterFailedCleanup($test, $result, $caught);
            }
        }
        foreach ($this->lifecycles as $lifecycle) {
            try {
                $lifecycle->afterTest($test, $result);
            } catch (\Throwable $caught) {
                $result = self::afterFailedCleanup($test, $result, $caught);
            }
        }

        $assertions = TestContext::assertions();
        TestContext::leave();

        return $result->withRunDetails(self::collectOutput($outputLevel), $assertions);
    }

    /**
     * Calls a hook with its fixtures, or directly when the suite has none.
     *
     * @param list<mixed> $positional
     */
    private static function call(?FixtureResolver $fixtures, \Closure $hook, array $positional): mixed
    {
        return $fixtures === null ? $hook(...$positional) : $fixtures->call($hook, $positional);
    }

    /** @return list<mixed> no argument for null, otherwise the value as the only argument */
    private static function valueAsArguments(mixed $value): array
    {
        return $value === null ? [] : [$value];
    }

    private static function afterFailedCleanup(Test $test, TestResult $result, \Throwable $throwable): TestResult
    {
        // A skipped test stays skipped when its cleanup asks for a fixture that skips again.
        $keepsResult = $result->status === TestStatus::Failed || ($result->status === TestStatus::Skipped && $throwable instanceof TestSkippedException);

        return $keepsResult ? $result : TestResult::failed($test, $result->time, $throwable);
    }

    /** Collects everything the test printed, including buffers it opened and forgot to close. */
    private static function collectOutput(int $outputLevel): string
    {
        $output = '';
        while (ob_get_level() > $outputLevel) {
            $output = ob_get_clean() . $output;
        }

        return $output;
    }
}
