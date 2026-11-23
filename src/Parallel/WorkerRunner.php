<?php

namespace MicroUnit\Parallel;

use MicroUnit\Core\SuiteRunner;
use MicroUnit\Core\TestPlan;
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

        // Every result is serialized on its own, keyed by test id: back-references stay inside one small
        // result, and a result the parent cannot read fails only its own test, not the whole share.
        $results = [];
        foreach ($summary->results as $result) {
            $results[$result->id] = serialize($result->forTransport());
        }

        Filesystem::writeAtomically($outputFile, serialize(['results' => $results, 'coverage' => serialize($coverage?->collect())]));
    }
}
