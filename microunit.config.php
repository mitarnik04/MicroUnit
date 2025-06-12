<?php

use MicroUnit\Config\MicroUnitConfigBuilder;
use MicroUnit\Output\MinimalStringTestWriter;

return MicroUnitConfigBuilder::create()
    ->withTestDir('./tests')
    ->addTestWriter(new MinimalStringTestWriter())
    ->withBootstrapFile('tests/bootstrap.php')
    ->build();
