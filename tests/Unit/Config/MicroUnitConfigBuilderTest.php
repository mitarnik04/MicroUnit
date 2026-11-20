<?php

use MicroUnit\Assertion\Assert;
use MicroUnit\Config\MicroUnitConfigBuilder;
use MicroUnit\Output\JUnitTestWriter;
use MicroUnit\Output\MinimalStringTestWriter;
use MicroUnit\Setup\TestSetup;

$tester = TestSetup::getTester('MicroUnitConfigBuilder');

$tester->define('add_test_writer_keeps_the_default_console_output', function (): void {
    $config = MicroUnitConfigBuilder::create()->addTestWriter(new JUnitTestWriter(sys_get_temp_dir() . '/junit.xml'))->build();

    Assert::countEquals(2, $config->testWriters);
    Assert::instanceOf(MinimalStringTestWriter::class, $config->testWriters[0]);
});

$tester->define('with_test_writers_replaces_them', function (): void {
    $config = MicroUnitConfigBuilder::create()->withTestWriters(new JUnitTestWriter(sys_get_temp_dir() . '/junit.xml'))->build();

    Assert::countEquals(1, $config->testWriters);
});

$tester->define('add_test_file_pattern_keeps_the_default', function (): void {
    Assert::exact(['*-tests.php', '*Test.php'], MicroUnitConfigBuilder::create()->addTestFilePattern('*Test.php')->build()->testFilePatterns);
    Assert::exact(['*Test.php'], MicroUnitConfigBuilder::create()->withTestFilePatterns('*Test.php')->build()->testFilePatterns);
});

$tester->define('strict_checks_and_the_integrity_check_are_off_by_default', function (): void {
    $config = MicroUnitConfigBuilder::create()->build();

    Assert::isNull($config->integrityCheck);
    Assert::isFalse($config->failOnRiskyTests);
    Assert::isNull($config->agentOutput);
});

$tester->define('integrity_check_settings', function (): void {
    $config = MicroUnitConfigBuilder::create()->withIntegrityCheck('origin/main', failOnFindings: true)->failOnRiskyTests()->build();

    Assert::exact(['origin/main', true, true], [$config->integrityCheck, $config->failOnWeakenedTests, $config->failOnRiskyTests]);
});

$tester->define('the_test_explorer_is_off_by_default_with_its_defaults_for_on_demand_use', function (): void {
    $config = MicroUnitConfigBuilder::create()->build();

    Assert::exact([false, 7357, 'vscode'], [$config->isTestExplorerEnabled, $config->testExplorerPort, $config->editor]);
});

$tester->define('with_test_explorer_sets_all_of_its_settings_in_one_call', function (): void {
    $opened = MicroUnitConfigBuilder::create()->withTestExplorer()->build();
    $onDemand = MicroUnitConfigBuilder::create()->withTestExplorer(openAfterRun: false, port: 7400, editor: 'phpstorm')->build();

    Assert::isTrue($opened->isTestExplorerEnabled);
    Assert::exact([false, 7400, 'phpstorm'], [$onDemand->isTestExplorerEnabled, $onDemand->testExplorerPort, $onDemand->editor]);
});
