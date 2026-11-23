<?php

use MicroUnit\Config\MicroUnitConfigBuilder;

// MicroUnit's own test suite, run by MicroUnit: php bin/microunit
$config = MicroUnitConfigBuilder::create()
    ->withTestGroup('unit', 'tests/Unit')
    ->withTestGroup('integration', 'tests/Integration')
    ->withTestFilePatterns('*Test.php')
    ->withBootstrapFile('tests/bootstrap.php');

// When an AI agent runs the suite, every run compares the tests with the last commit and tells the
// agent right away when a test got weaker (removed assertions, changed expected values, new skips).
if (getenv('CLAUDECODE') === '1' || getenv('MICROUNIT_AGENT') === '1') {
    $config->withIntegrityCheck();
}

// In CI, every run measures its own coverage of src/ and fails below the floor. The floor sits just
// under the current value, so coverage can only go up.
if (getenv('CI') !== false) {
    $config->withCoverage(include: 'src', minimum: 47);
}

return $config->build();
