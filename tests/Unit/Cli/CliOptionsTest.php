<?php

use MicroUnit\Assertion\Assert;
use MicroUnit\Cli\CliOptions;
use MicroUnit\Cli\UsageException;
use MicroUnit\Setup\TestSetup;

$tester = TestSetup::getTester('CliOptions');

$tester->define('values_follow_an_equals_sign_or_come_as_the_next_argument', function (): void {
    $joined = CliOptions::parse(['--filter=Cart', '--config=ci.php', '--group=unit,feature']);
    $separate = CliOptions::parse(['--filter', 'Cart', '-c', 'ci.php', '--group', 'unit,feature']);

    Assert::exact([$joined->filter, $joined->configFile, $joined->groups], [$separate->filter, $separate->configFile, $separate->groups]);
    Assert::exact(['Cart', 'ci.php', ['unit', 'feature']], [$separate->filter, $separate->configFile, $separate->groups]);
});

$tester->define('optional_values_only_take_an_equals_sign', function (): void {
    $options = CliOptions::parse(['--parallel', 'tests/Unit']);

    Assert::exact(0, $options->parallelWorkers);
    Assert::exact(['tests/Unit'], $options->paths);
});

$tester->define('commands_come_first', function (): void {
    Assert::exact('migrate', CliOptions::parse(['migrate', 'tests', '--out=new'])->command);
    Assert::exact('new', CliOptions::parse(['migrate', 'tests', '--out', 'new'])->outputDirectory);
});

$tester->define('help_for_a_command', function (): void {
    $options = CliOptions::parse(['migrate', '--help']);

    Assert::exact(['help', 'migrate'], [$options->command, $options->helpTopic]);
});

$tester->define('unknown_options_are_usage_errors', function (): void {
    Assert::throws(fn() => CliOptions::parse(['--nope']), UsageException::class, "Unknown option '--nope'");
    Assert::throws(fn() => CliOptions::parse(['--list=xml']), UsageException::class);
});
