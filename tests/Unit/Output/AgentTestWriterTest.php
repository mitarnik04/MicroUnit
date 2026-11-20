<?php

use MicroUnit\Assertion\Assert;
use MicroUnit\Core\Test;
use MicroUnit\Core\TestResult;
use MicroUnit\Exceptions\TestFailedException;
use MicroUnit\Output\AgentTestWriter;
use MicroUnit\Setup\TestSetup;

$tester = TestSetup::getTester('AgentTestWriter');

/** The JSON an AgentTestWriter prints for the given results. @param list<TestResult> $results */
function agentReport(array $results): array
{
    $writer = new AgentTestWriter(projectDirectory: dirname(__DIR__, 3));
    $writer->writeResults($results);
    $passed = count(array_filter($results, fn(TestResult $result) => $result->status->value === 'passed'));
    $failed = count(array_filter($results, fn(TestResult $result) => $result->status->value === 'failed'));
    ob_start();
    $writer->writeSummary(count($results), $passed, $failed);

    return json_decode((string) ob_get_clean(), true, flags: JSON_THROW_ON_ERROR);
}

function sampleTest(string $name, int $line): Test
{
    return new Test("Cart::$name", 'Cart', $name, fn() => null, [], __FILE__, $line);
}

$tester->define('passing_runs_cost_almost_no_output', function (): void {
    $report = agentReport([TestResult::passed(sampleTest('adds', 1), 0.001)->withRunDetails('', 2)]);

    Assert::exact(['result' => 'passed', 'tests' => 1, 'passed' => 1, 'failed' => 0, 'skipped' => 0], array_diff_key($report, ['seconds' => 0]));
});

$tester->define('failures_carry_location_values_and_code', function (): void {
    $failure = Assert::throws(fn() => Assert::exact(3, 2), TestFailedException::class);
    $line = __LINE__ - 1;
    $report = agentReport([TestResult::failed(sampleTest('counts', $line), 0.001, $failure)]);
    $entry = $report['failures'][0];

    Assert::exact('Cart::counts', $entry['test']);
    Assert::stringEndsWith("AgentTestWriterTest.php:$line", $entry['at']);
    Assert::exact('3 (int)', $entry['expected']);
    Assert::exact('2 (int)', $entry['actual']);
    Assert::contains("$line>     \$failure = Assert::throws(fn() => Assert::exact(3, 2), TestFailedException::class);", $entry['code']);
});

$tester->define('errors_have_a_trace_without_framework_frames', function (): void {
    $report = agentReport([TestResult::failed(sampleTest('breaks', __LINE__), 0.001, new RuntimeException('disk full'))]);
    $entry = $report['failures'][0];

    Assert::exact('RuntimeException: disk full', $entry['error']);
    Assert::each($entry['trace'] ?? [], fn(string $frame) => !str_contains($frame, 'src/Core/'));
});

$tester->define('risky_tests_are_reported_as_warnings', function (): void {
    $report = agentReport([TestResult::passed(sampleTest('proves_nothing', 1), 0.001)]);

    Assert::exact([['test' => 'Cart::proves_nothing', 'warning' => 'passed without a single assertion']], $report['warnings']);
});

$tester->define('integrity_findings_are_included', function (): void {
    $writer = new AgentTestWriter(projectDirectory: __DIR__)->withIntegrityFindings(fn() => [['test' => 'Cart::adds', 'file' => 'tests/cart-tests.php', 'line' => 8, 'finding' => 'now skipped']]);
    ob_start();
    $writer->writeSummary(0, 0, 0);
    $report = json_decode((string) ob_get_clean(), true);

    Assert::exact([['test' => 'Cart::adds', 'at' => 'tests/cart-tests.php:8', 'finding' => 'now skipped']], $report['weakenedTests']);
});

$tester->define('integrity_findings_name_the_compared_revision', function (): void {
    $writer = new AgentTestWriter(projectDirectory: __DIR__)->withIntegrityFindings(fn() => [['test' => 'Cart::adds', 'file' => 'a.php', 'line' => 1, 'finding' => 'removed']], 'origin/main');
    ob_start();
    $writer->writeSummary(0, 0, 0);

    Assert::stringStartsWith('Compared to origin/main.', json_decode((string) ob_get_clean(), true)['weakenedTestsNote']);
});

$tester->define('a_run_failing_for_other_reasons_is_not_reported_as_passed', function (): void {
    $writer = new AgentTestWriter(projectDirectory: __DIR__)->withRunFailures(fn() => ['line coverage 50.0% is below the minimum of 80.0%']);
    ob_start();
    $writer->writeSummary(1, 1, 0);
    $report = json_decode((string) ob_get_clean(), true);

    Assert::exact('failed', $report['result']);
    Assert::exact(['line coverage 50.0% is below the minimum of 80.0%'], $report['failedBecause']);
});
