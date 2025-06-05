<?php
require_once __DIR__ . '/src/config/config-builder.php';

return MicroUnitConfigBuilder::begin()
    ->withTestDir('./')
    ->addTestWriter(new MinimalStringTestWriter())
    ->addTestFilePattern('*Tests.php')
    ->stopOnFailure()
    ->build();
