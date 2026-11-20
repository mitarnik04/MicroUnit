<?php

use MicroUnit\Assertion\Assert;
use MicroUnit\Setup\TestSetup;
use MicroUnit\Tests\Support\Cli;

$tester = TestSetup::getTester('Application');

$tester->define('stop_on_failure_also_stops_every_parallel_worker', function (): void {
    $run = Cli::run('cli', ['failing', '--parallel=2', '--stop-on-failure', '--no-colors']);

    Assert::exact(1, $run->exitCode);
    Assert::stringContains('Summary: 0/2 passed, 2 failed', $run->output);
});

$tester->define('parallel_workers_get_the_same_paths_even_one_named_0', function (): void {
    $run = Cli::run('cli', ['0', '--parallel=2', '--no-colors']);

    Assert::exact(0, $run->exitCode);
    Assert::stringContains('Summary: 2/2 passed', $run->output);
});

$tester->define('the_colors_flag_wins_over_no_color', function (): void {
    $run = Cli::run('cli', ['preload', '--colors'], ['NO_COLOR' => '1', 'FORCE_COLOR' => null]);

    Assert::stringContains("\e[", $run->output);
});

$tester->define('the_no_colors_flag_wins_over_force_color', function (): void {
    $run = Cli::run('cli', ['preload', '--no-colors'], ['FORCE_COLOR' => '1', 'NO_COLOR' => null]);

    Assert::isFalse(str_contains($run->output, "\e["));
});

$tester->define('the_first_test_is_not_charged_for_loading_the_assertions', function (): void {
    $run = Cli::run('cli', ['preload', '--no-colors']);

    Assert::exact(0, $run->exitCode, $run->output);
});

$tester->define('a_crashed_worker_fails_its_tests_at_their_own_location', function (): void {
    $run = Cli::run('cli', ['crash', '--parallel=2', '--no-colors']);

    Assert::exact(1, $run->exitCode);
    Assert::stringContains('stopped unexpectedly (exit code 3)', $run->output);
    Assert::stringContains('crash-tests.php:7', $run->output);
    Assert::isFalse(str_contains($run->output, 'ParallelRunner.php'));
});

$tester->define('a_project_without_a_test_directory_is_told_how_to_create_one', function (): void {
    $run = Cli::run('no-tests', ['--no-colors']);

    Assert::exact(2, $run->exitCode);
    Assert::stringContains("Run 'microunit init' to create it", $run->errors . $run->output);
});
