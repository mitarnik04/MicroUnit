<?php

use MicroUnit\Config\MicroUnitConfigBuilder;

return MicroUnitConfigBuilder::create()
    ->withTestDir('tests')
    ->withCoverage(include: 'src', html: '../../../../.microunit/fixture-coverage', clover: '../../../../.microunit/fixture-clover.xml', cobertura: '../../../../.microunit/fixture-cobertura.xml')
    ->withRuntimeDirectory('../../../../.microunit/fixture-runtime')
    ->build();
