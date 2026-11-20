<?php

namespace MicroUnit\Parallel;

use MicroUnit\Core\SuiteRunner;
use MicroUnit\Core\TestPlan;
use MicroUnit\Core\TestResult;
use MicroUnit\Core\TestRunner;
use MicroUnit\Coverage\CoverageSession;
use MicroUnit\Helpers\Filesystem;

/** Runs inside a worker process: executes its share of the plan and reports back through a file. */
final readonly class WorkerRunner
{
    public function __construct(
        private TestRunner $testRunner,
        private bool $stopOnFailure,
    ) {}

    public function run(TestPlan $share, string $outputFile, ?CoverageSession $coverage): void
    {
        $summary = new SuiteRunner($this->testRunner, $this->stopOnFailure)->run($share, static function (): void {});

        $result = new WorkerResult(
            array_map(static fn(TestResult $result): TestResult => $result->forTransport(), $summary->results),
            $coverage?->collect(),
        );

        Filesystem::writeAtomically($outputFile, serialize($result));
    }
}
