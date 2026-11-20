<?php

namespace MicroUnit\Core;

use MicroUnit\Assertion\Assert;
use MicroUnit\Exceptions\TestSkippedException;

/** Runs a TestPlan in order, suite by suite, including each suite's once-per-suite setup and cleanup. */
final readonly class SuiteRunner
{
    /** @param resource $diagnostics where problems outside any single test (a failing suite cleanup) are reported */
    public function __construct(
        private TestRunner $testRunner = new TestRunner(),
        private bool $stopOnFailure = false,
        private mixed $diagnostics = STDERR,
    ) {}

    /** @param \Closure(string $suite, list<TestResult> $results): void $onSuiteFinished */
    public function run(TestPlan $plan, \Closure $onSuiteFinished): RunSummary
    {
        // Loaded up front so the first test's time does not include autoloading the assertions.
        class_exists(Assert::class);
        $summary = new RunSummary();

        foreach ($plan->bySuite() as [$tester, $tests]) {
            $results = $this->runSuite($tester, $tests);
            $summary->add($results);
            $onSuiteFinished($tester->suite, $results);

            if ($this->stopOnFailure && $summary->hasFailures) {
                break;
            }
        }

        return $summary;
    }

    /**
     * @param list<Test> $tests
     * @return list<TestResult>
     */
    private function runSuite(Tester $tester, array $tests): array
    {
        try {
            $suite = new SuiteContext($tester, $this->quietly($tester->getSetUpOnce() ?? static fn() => null));
        } catch (\Throwable $throwable) {
            // Without its shared setup no test of the suite can run meaningfully.
            return array_map(static fn(Test $test): TestResult => self::resultForBrokenSetUp($test, $tester, $throwable), $tests);
        }

        $results = [];
        foreach ($tests as $test) {
            $result = $this->testRunner->run($test, $suite);
            $results[] = $result;
            if ($this->stopOnFailure && $result->status === TestStatus::Failed) {
                break;
            }
        }

        $this->finishSuite($suite);

        return $results;
    }

    /**
     * Cleans up shared fixtures and runs tearDownOnce(), each even when the other fails (a broken
     * fixture cleanup must not leave the suite's server running). Failures are reported, not swallowed.
     */
    private function finishSuite(SuiteContext $suite): void
    {
        $tester = $suite->tester;
        try {
            $this->quietly(static fn() => $suite->sharedFixtures->cleanUp($tester->getFixtures()));
        } catch (\Throwable $throwable) {
            $this->report("Cleanup after suite '{$tester->suite}' failed: {$throwable->getMessage()}");
        }

        $tearDownOnce = $tester->getTearDownOnce();
        if ($tearDownOnce === null) {
            return;
        }
        try {
            $this->quietly(static fn() => $tearDownOnce($suite->sharedValue));
        } catch (\Throwable $throwable) {
            $this->report("tearDownOnce() of suite '{$tester->suite}' failed: {$throwable->getMessage()}");
        }
    }

    private function report(string $problem): void
    {
        fwrite($this->diagnostics, $problem . PHP_EOL);
    }

    private static function resultForBrokenSetUp(Test $test, Tester $tester, \Throwable $throwable): TestResult
    {
        if ($throwable instanceof TestSkippedException) {
            return TestResult::skipped($test, $throwable->getMessage());
        }

        return TestResult::failed($test, 0.0, new \RuntimeException(
            "setUpOnce() of suite '{$tester->suite}' failed: {$throwable->getMessage()}",
            0,
            $throwable,
        ));
    }

    /** Runs a hook without letting its output interleave with the writers' output. */
    private function quietly(\Closure $hook): mixed
    {
        ob_start();
        try {
            return $hook();
        } finally {
            ob_end_clean();
        }
    }
}
