<?php

namespace MicroUnit\Core;

/** Totals of a test run. */
final class RunSummary
{
    public private(set) int $total = 0;

    public private(set) int $passed = 0;

    public private(set) int $failed = 0;

    public private(set) int $skipped = 0;

    /** @var list<TestResult> every result in definition order */
    public private(set) array $results = [];

    public bool $hasFailures {
        get => $this->failed > 0;
    }

    /** @param list<TestResult> $results */
    public function add(array $results): void
    {
        foreach ($results as $result) {
            $this->results[] = $result;
            $this->total++;
            match ($result->status) {
                TestStatus::Passed => $this->passed++,
                TestStatus::Failed => $this->failed++,
                TestStatus::Skipped => $this->skipped++,
            };
        }
    }

    /**
     * Wall-clock seconds since the process started: what the user actually waited for, including
     * bootstrapping, setUp/tearDown and parallel workers (not the sum of test bodies).
     */
    public static function elapsedSeconds(): float
    {
        /** @var float $startTime PHP sets it when the process starts */
        $startTime = $_SERVER['REQUEST_TIME_FLOAT'];

        return microtime(true) - $startTime;
    }
}
