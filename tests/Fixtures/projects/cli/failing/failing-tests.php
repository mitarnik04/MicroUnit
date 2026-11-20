<?php

use MicroUnit\Assertion\Assert;
use MicroUnit\Setup\TestSetup;

foreach (['First', 'Second'] as $suite) {
    $tester = TestSetup::getTester($suite);
    foreach (['one', 'two', 'three'] as $name) {
        $tester->define("fails_$name", fn() => Assert::fail("$suite $name"));
    }
}
