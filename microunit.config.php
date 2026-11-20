<?php

use MicroUnit\Config\MicroUnitConfigBuilder;

// MicroUnit's own test suite, run by MicroUnit: php bin/microunit
return MicroUnitConfigBuilder::create()
    ->withTestGroup('unit', 'tests/Unit')
    ->withTestGroup('integration', 'tests/Integration')
    ->withTestFilePatterns('*Test.php')
    ->withBootstrapFile('tests/bootstrap.php')
    ->build();
