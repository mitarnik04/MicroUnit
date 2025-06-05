<?php
return MicroUnitConfigBuilder::create()
    ->withTestDir('./')
    ->withBootstrapFile('bootstrap.php')
    ->addTestWriter(new MinimalStringTestWriter())
    ->build();
