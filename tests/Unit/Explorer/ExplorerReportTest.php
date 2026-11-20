<?php

use MicroUnit\Assertion\Assert;
use MicroUnit\Core\TestResult;
use MicroUnit\Core\TestStatus;
use MicroUnit\Explorer\ExplorerReport;
use MicroUnit\Setup\TestSetup;

$tester = TestSetup::getTester('ExplorerReport');

$tester->define('lists_the_results_by_suite', function (): void {
    $report = ExplorerReport::fromResults([
        new TestResult('Cart::adds', 'Cart', 'adds', TestStatus::Passed, 0.001),
        new TestResult('Cart::skips', 'Cart', 'skips', TestStatus::Skipped, 0.0, skipReason: 'later'),
    ], 'vscode');

    Assert::exact(1, $report['version']);
    Assert::exact(['total' => 2, 'passed' => 1, 'failed' => 0, 'skipped' => 1, 'notrun' => 0], $report['summary']);
    Assert::exact('Cart', $report['suites'][0]['name']);
    Assert::exact(['adds', 'skips'], array_column($report['suites'][0]['tests'], 'name'));
    Assert::exact([1, 2], array_column($report['suites'][0]['tests'], 'channel'));
});

$tester->define('long_output_is_shortened', function (): void {
    $report = ExplorerReport::fromResults([
        new TestResult('Cart::prints', 'Cart', 'prints', TestStatus::Passed, 0.001, output: str_repeat('x', 150_000)),
    ], 'vscode');

    $output = $report['suites'][0]['tests'][0]['output'];
    Assert::isLessThan(100_100, strlen($output));
    Assert::stringEndsWith('(50000 more bytes)', $output);
});
