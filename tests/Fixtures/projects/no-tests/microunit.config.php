<?php

use MicroUnit\Config\MicroUnitConfigBuilder;

// Used by tests/Integration/Cli: a project without a tests/ directory and without withTestDir().
return MicroUnitConfigBuilder::create()
    ->withRuntimeDirectory('../../../../.microunit/fixture-no-tests-runtime')
    ->build();
