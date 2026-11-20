<?php

use MicroUnit\Config\MicroUnitConfigBuilder;

// Used by tests/Integration/Cli: each directory holds the tests one scenario runs (passed as a path).
return MicroUnitConfigBuilder::create()
    ->withTestDir('tests')
    ->withRuntimeDirectory('../../../../.microunit/fixture-cli-runtime')
    ->build();
