<?php

use MicroUnit\Assertion\Assert;
use MicroUnit\Core\LastRun;
use MicroUnit\Core\RunSummary;
use MicroUnit\Core\Test;
use MicroUnit\Core\TestResult;
use MicroUnit\Exceptions\TestFailedException;
use MicroUnit\Setup\TestSetup;
use MicroUnit\Toolkit\TemporaryDirectory;

$tester = TestSetup::getTester('LastRun');

$tester->fixture('directory', fn() => TemporaryDirectory::create(), cleanup: fn(TemporaryDirectory $directory) => $directory->delete());

/** A summary with the given tests, failed or passed. @param array<string, bool> $hasFailed id => whether it failed */
function summaryOf(array $hasFailed): RunSummary
{
    $summary = new RunSummary();
    $summary->add(array_map(static function (string $id, bool $failed): TestResult {
        $test = new Test($id, 'Cart', substr($id, strlen('Cart::')), fn() => null, [], __FILE__, 1);

        return $failed
            ? TestResult::failed($test, 0.001, Assert::throws(fn() => Assert::fail('no'), TestFailedException::class))
            : TestResult::passed($test, 0.001);
    }, array_keys($hasFailed), $hasFailed));

    return $summary;
}

$tester->define('nothing_is_known_before_the_first_run', function (TemporaryDirectory $directory): void {
    $lastRun = new LastRun($directory->path('last-run.json'));

    Assert::exact([], $lastRun->failedTestIds());
    Assert::isNull($lastRun->estimatedSeconds(10));
});

$tester->define('remembers_the_failed_tests_and_the_time_per_test', function (TemporaryDirectory $directory): void {
    $lastRun = new LastRun($directory->path('last-run.json'));

    $lastRun->save(summaryOf(['Cart::adds' => true, 'Cart::totals' => false]));

    Assert::exact(['Cart::adds'], $lastRun->failedTestIds());
    Assert::notNull($lastRun->estimatedSeconds(2));
});

$tester->define('a_partial_run_keeps_the_failures_of_tests_it_did_not_run', function (TemporaryDirectory $directory): void {
    $lastRun = new LastRun($directory->path('last-run.json'));
    $lastRun->save(summaryOf(['Cart::adds' => true, 'Cart::removes' => true]));

    $lastRun->save(summaryOf(['Cart::adds' => false]));

    Assert::exact(['Cart::removes'], $lastRun->failedTestIds());
});

$tester->define('estimates_by_the_time_per_test_so_a_small_run_does_not_speak_for_a_large_one', function (TemporaryDirectory $directory): void {
    $lastRun = new LastRun($directory->write('last-run.json', '{"durationSeconds":0.5,"tests":10,"failed":[]}'));

    Assert::equalsWithDelta(0.05, $lastRun->estimatedSeconds(1), 1e-9);
    Assert::equalsWithDelta(50.0, $lastRun->estimatedSeconds(1000), 1e-9);
});

$tester->define('a_file_without_a_test_count_estimates_nothing', function (TemporaryDirectory $directory): void {
    $lastRun = new LastRun($directory->write('last-run.json', '{"durationSeconds":0.5,"failed":[]}'));

    Assert::isNull($lastRun->estimatedSeconds(1000));
});
