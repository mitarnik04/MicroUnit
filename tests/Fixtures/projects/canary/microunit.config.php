<?php

use MicroUnit\Config\MicroUnitConfigBuilder;

// Used by bin/canary.php: tests whose outcomes are known, so the canary can check MicroUnit reports them.
return MicroUnitConfigBuilder::create()
    ->withTestDir('tests')
    ->withRuntimeDirectory('../../../../.microunit/fixture-canary-runtime')
    ->build();
