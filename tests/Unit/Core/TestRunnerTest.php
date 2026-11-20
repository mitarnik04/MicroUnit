<?php

use MicroUnit\Assertion\Assert;
use MicroUnit\Core\SuiteContext;
use MicroUnit\Core\Tester;
use MicroUnit\Core\TestResult;
use MicroUnit\Core\TestRunner;
use MicroUnit\Core\TestStatus;
use MicroUnit\Setup\TestSetup;

$tester = TestSetup::getTester('TestRunner');

/** Runs the only test of a standalone (unregistered) Tester. */
function runOnlyTest(Tester $sample): TestResult
{
    return new TestRunner()->run($sample->getTests()[0], new SuiteContext($sample));
}

$tester->define('set_up_receives_fixtures_by_name_and_shares_them_with_the_test', function (): void {
    $sample = new Tester('Sample');
    $sample->fixture('captcha', fn() => new ArrayObject(['solved' => true]));
    $seenInSetUp = null;
    $seenInTest = null;
    $sample->setUp(function (ArrayObject $captcha) use (&$seenInSetUp): void {
        $seenInSetUp = $captcha;
    });
    $sample->define('uses_the_captcha', function (ArrayObject $captcha) use (&$seenInTest): void {
        $seenInTest = $captcha;
    });

    $result = runOnlyTest($sample);

    Assert::exact(TestStatus::Passed, $result->status);
    Assert::notNull($seenInSetUp);
    Assert::exact($seenInSetUp, $seenInTest);
});

$tester->define('tear_down_receives_the_result_and_fixtures', function (): void {
    $sample = new Tester('Sample');
    $sample->fixture('uploads', fn() => new ArrayObject());
    $tornDown = [];
    $sample->tearDown(function (TestResult $result, mixed $setUpResult, ArrayObject $uploads) use (&$tornDown): void {
        $tornDown = [$result->status, count($uploads)];
    });
    $sample->define('writes_an_upload', function (ArrayObject $uploads): void {
        $uploads[] = 'file.png';
    });

    runOnlyTest($sample);

    Assert::exact([TestStatus::Passed, 1], $tornDown);
});

$tester->define('set_up_can_replace_a_fixture_taken_by_reference', function (): void {
    $sample = new Tester('Sample');
    $sample->fixture('now', fn(): ?string => null);
    $sample->setUp(function (?string &$now): void {
        $now = '2026-01-01';
    });
    $sample->define('reads_the_replaced_value', fn(?string $now) => $now);

    Assert::exact('2026-01-01', runOnlyTest($sample)->result);
});

$tester->define('fixtures_are_fresh_for_every_test', function (): void {
    $sample = new Tester('Sample');
    $sample->fixture('counter', fn() => new ArrayObject([0]));
    $sample->define('increments', function (ArrayObject $counter): int {
        return ++$counter[0];
    });

    $runner = new TestRunner();
    $context = new SuiteContext($sample);
    $test = $sample->getTests()[0];

    Assert::exact(1, $runner->run($test, $context)->result);
    Assert::exact(1, $runner->run($test, $context)->result);
});

$tester->define('a_test_skipped_by_a_fixture_stays_skipped_when_tear_down_asks_for_it', function (): void {
    $sample = new Tester('Sample');
    $sample->fixture('server', fn() => Assert::skip('No server'));
    $sample->tearDown(function ($result, $setUp, $server): void {});
    $sample->define('talks_to_the_server', function ($server): void {});

    $result = runOnlyTest($sample);

    Assert::exact(TestStatus::Skipped, $result->status);
});

$tester->define('a_shared_fixture_cannot_use_a_per_test_fixture', function (): void {
    $sample = new Tester('Sample');
    $sample->fixture('database', fn() => new ArrayObject(['open' => true]), fn(ArrayObject $database) => $database['open'] = false);
    $sample->fixture('repository', fn(ArrayObject $database) => $database, shared: true);
    $sample->define('reads', fn(ArrayObject $repository) => $repository['open']);

    $result = runOnlyTest($sample);

    Assert::exact(TestStatus::Failed, $result->status);
    Assert::stringContains("The shared fixture 'repository' cannot use the per-test fixture 'database'", (string) $result->errorMsg);
});

$tester->define('default_values_are_created_anew_for_every_test', function (): void {
    $sample = new Tester('Sample');
    $sample->fixture('unused', fn() => null);
    $sample->define('collects', function (ArrayObject $bag = new ArrayObject()): ArrayObject {
        $bag[] = 'item';

        return $bag;
    });

    $runner = new TestRunner();
    $context = new SuiteContext($sample);
    $test = $sample->getTests()[0];

    Assert::countEquals(1, $runner->run($test, $context)->result);
    Assert::countEquals(1, $runner->run($test, $context)->result);
});

$tester->define('hooks_of_a_suite_without_fixtures_get_their_values_directly', function (): void {
    $sample = new Tester('Sample');
    $tornDown = null;
    $sample->setUp(fn() => 'from set up');
    $sample->tearDown(function (TestResult $result, string $setUpResult) use (&$tornDown): void {
        $tornDown = [$result->status, $setUpResult];
    });
    $sample->define('receives', fn(string $value) => $value);

    $result = runOnlyTest($sample);

    Assert::exact('from set up', $result->result);
    Assert::exact([TestStatus::Passed, 'from set up'], $tornDown);
});

$tester->define('a_skipped_test_keeps_the_time_it_ran_before_skipping', function (): void {
    $sample = new Tester('Sample');
    $sample->define('waits_then_skips', function (): void {
        usleep(20_000);
        Assert::skip('the server is not reachable');
    });

    $result = runOnlyTest($sample);

    Assert::exact(TestStatus::Skipped, $result->status);
    Assert::isGreaterThanOrEqual(0.015, $result->time);
});
