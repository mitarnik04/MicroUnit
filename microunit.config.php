<?php

use MicroUnit\Config\MicroUnitConfigBuilder;
use MicroUnit\Output\StringTestWriter;

return MicroUnitConfigBuilder::create()
    ->withTestDir('./tmp-tests')
    ->addTestWriter(new StringTestWriter())
    ->build();
