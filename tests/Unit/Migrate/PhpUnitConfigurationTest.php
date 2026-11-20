<?php

use MicroUnit\Assertion\Assert;
use MicroUnit\Migrate\PhpUnitConfiguration;
use MicroUnit\Setup\TestSetup;

$tester = TestSetup::getTester('PhpUnitConfiguration');

$tester->define('reads_bootstrap_environment_and_ini', function (): void {
    $configuration = PhpUnitConfiguration::parse('<phpunit bootstrap="./tests/bootstrap.php"><php><env name="APP_ENV" value="test"/><ini name="memory_limit" value="-1"/></php></phpunit>');

    Assert::exact('tests/bootstrap.php', $configuration->bootstrap);
    Assert::exact(['APP_ENV' => 'test'], $configuration->environment);
    Assert::exact(['memory_limit' => '-1'], $configuration->ini);
});

$tester->define('a_bootstrap_in_a_parent_directory_keeps_its_path', function (): void {
    Assert::exact('../bootstrap.php', PhpUnitConfiguration::parse('<phpunit bootstrap="../bootstrap.php">')->bootstrap);
});

$tester->define('the_environment_becomes_a_constructor_argument', function (): void {
    Assert::exact("environment: ['APP_ENV' => 'test']", PhpUnitConfiguration::parse('<phpunit><env name="APP_ENV" value="test"/></phpunit>')->environmentArgument());
    Assert::exact('', PhpUnitConfiguration::parse('<phpunit/>')->environmentArgument());
});
