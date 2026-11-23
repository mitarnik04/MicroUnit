<?php

/**
 * Checks that MicroUnit still tells a passing test from a failing one, without loading any of its code.
 *
 *     php bin/canary.php
 *
 * MicroUnit tests itself, and that loop catches whatever breaks, except a break in how failures are
 * reported at all: an assertion that always passes, a swallowed exception, an exit code that is always 0.
 * So this runs first in CI: it runs MicroUnit on tests whose outcomes are known and checks the exit
 * code and the report with plain PHP.
 */

const FIXTURE = __DIR__ . '/../tests/Fixtures/projects/canary';
const FAILING_TESTS = [
    'Canary::a_failing_assertion_fails',
    'Canary::an_expected_exception_that_is_never_thrown_fails',
    'Canary::an_unexpected_exception_fails',
];

$problems = [
    ...check('every test', [], exitCode: 1, passed: 1, failed: FAILING_TESTS),
    ...check('only the passing test', ['--filter=Canary::passes'], exitCode: 0, passed: 1, failed: []),
];

if ($problems === []) {
    echo '✔ Canary passed: MicroUnit tells passing tests from failing ones.' . PHP_EOL;
    exit(0);
}

echo implode(PHP_EOL, $problems) . PHP_EOL . PHP_EOL . '✖ Canary failed: MicroUnit no longer reports test outcomes correctly.' . PHP_EOL;
exit(1);

/**
 * Runs MicroUnit on the canary fixture and compares what it reports with what is known.
 *
 * @param list<string> $arguments
 * @param list<string> $failed the ids of the tests that must be reported as failed
 * @return list<string> the differences
 */
function check(string $run, array $arguments, int $exitCode, int $passed, array $failed): array
{
    $process = proc_open([PHP_BINARY, __DIR__ . '/microunit', '--agent', ...$arguments], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, FIXTURE);
    if ($process === false) {
        return ["$run: could not start MicroUnit"];
    }
    $output = (string) stream_get_contents($pipes[1]);
    $errors = (string) stream_get_contents($pipes[2]);
    $actualExitCode = proc_close($process);

    $report = json_decode($output, true);
    if (!is_array($report)) {
        return ["$run: MicroUnit did not print a JSON report. Output:\n$output$errors"];
    }
    $reportedFailed = array_column(is_array($report['failures'] ?? null) ? $report['failures'] : [], 'test');
    sort($reportedFailed);
    sort($failed);

    $problems = [];
    if ($actualExitCode !== $exitCode) {
        $problems[] = "$run: exit code $actualExitCode, expected $exitCode";
    }
    if (($report['passed'] ?? null) !== $passed) {
        $problems[] = "$run: " . json_encode($report['passed'] ?? null) . " passed, expected $passed";
    }
    if (($report['failed'] ?? null) !== count($failed) || $reportedFailed !== $failed) {
        $problems[] = "$run: reported as failed " . json_encode($reportedFailed) . ', expected ' . json_encode($failed);
    }

    return $problems;
}
