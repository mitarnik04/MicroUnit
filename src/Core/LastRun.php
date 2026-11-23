<?php

namespace MicroUnit\Core;

use MicroUnit\Helpers\Filesystem;

/**
 * What the next run needs to know about the previous ones: the tests that are still failing (for
 * --failed) and how long the last run took per test (to decide whether running in parallel pays
 * off). A few bytes, written after every run, independent of the Test Explorer.
 */
final readonly class LastRun
{
    public function __construct(
        private string $file,
    ) {}

    /** Tests that ran this time take their new status; failures of tests that did not run are kept. */
    public function save(RunSummary $summary): void
    {
        $ran = [];
        $failed = [];
        foreach ($summary->results as $result) {
            $ran[$result->id] = true;
            if ($result->status === TestStatus::Failed) {
                $failed[] = $result->id;
            }
        }
        $stillFailing = array_filter($this->failedTestIds(), static fn(string $id): bool => !isset($ran[$id]));

        Filesystem::writeAtomically($this->file, (string) json_encode(
            ['durationSeconds' => RunSummary::elapsedSeconds(), 'tests' => $summary->total, 'failed' => [...$stillFailing, ...$failed]],
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE,
        ));
    }

    /** @return list<string> */
    public function failedTestIds(): array
    {
        $failed = $this->read()['failed'] ?? [];

        return is_array($failed) ? array_values(array_filter($failed, is_string(...))) : [];
    }

    /**
     * How long $testCount tests would take, judged by the last run's time per test: the last run
     * may have been a single filtered test or the whole suite. Null when nothing is known yet.
     */
    public function estimatedSeconds(int $testCount): ?float
    {
        $lastRun = $this->read();
        $seconds = $lastRun['durationSeconds'] ?? null;
        $tests = $lastRun['tests'] ?? null;
        if (!(is_int($seconds) || is_float($seconds)) || !is_int($tests) || $tests < 1) {
            return null;
        }

        return $seconds / $tests * $testCount;
    }

    /** @return array<mixed> */
    private function read(): array
    {
        $decoded = is_file($this->file) ? json_decode((string) file_get_contents($this->file), true) : null;

        return is_array($decoded) ? $decoded : [];
    }
}
