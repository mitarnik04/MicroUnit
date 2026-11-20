<?php

namespace MicroUnit\Parallel;

use MicroUnit\Core\TestResult;
use MicroUnit\Coverage\CoverageData;

/** What a worker process sends back to the parent. */
final readonly class WorkerResult
{
    /** @param list<TestResult> $results already prepared with TestResult::forTransport() */
    public function __construct(
        public array $results,
        public ?CoverageData $coverage,
    ) {}
}
