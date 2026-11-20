<?php

use MicroUnit\Assertion\Assert;
use MicroUnit\Core\SuiteRunner;
use MicroUnit\Core\Tester;
use MicroUnit\Core\TestFilter;
use MicroUnit\Core\TestPlan;
use MicroUnit\Core\TestRunner;
use MicroUnit\Setup\TestSetup;

$tester = TestSetup::getTester('SuiteRunner');

$tester->define('tear_down_once_runs_even_when_a_shared_fixture_cleanup_fails', function (): void {
    $sample = new Tester('Sample');
    $sample->fixture('server', fn() => 'running', fn() => throw new RuntimeException('port still in use'), shared: true);
    $hasTornDown = false;
    $sample->tearDownOnce(function () use (&$hasTornDown): void {
        $hasTornDown = true;
    });
    $sample->define('uses_the_server', fn(string $server) => Assert::exact('running', $server));
    $diagnostics = fopen('php://memory', 'w+');

    new SuiteRunner(new TestRunner(), diagnostics: $diagnostics)->run(TestPlan::create([$sample], TestFilter::all()), static function (): void {});

    Assert::isTrue($hasTornDown);
    rewind($diagnostics);
    Assert::exact("Cleanup after suite 'Sample' failed: Cleanup of fixture 'server' failed: port still in use" . PHP_EOL, stream_get_contents($diagnostics));
});

$tester->define('a_failing_tear_down_once_is_reported', function (): void {
    $sample = new Tester('Sample');
    $sample->tearDownOnce(fn() => throw new RuntimeException('container would not stop'));
    $sample->define('passes', fn() => Assert::isTrue(true));
    $diagnostics = fopen('php://memory', 'w+');

    new SuiteRunner(new TestRunner(), diagnostics: $diagnostics)->run(TestPlan::create([$sample], TestFilter::all()), static function (): void {});

    rewind($diagnostics);
    Assert::exact("tearDownOnce() of suite 'Sample' failed: container would not stop" . PHP_EOL, stream_get_contents($diagnostics));
});
