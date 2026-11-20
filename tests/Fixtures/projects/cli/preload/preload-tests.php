<?php

use MicroUnit\Assertion\Assert;
use MicroUnit\Setup\TestSetup;

// Nothing before this test may touch Assert: the runner has to load it before timing the first test.
TestSetup::getTester('Preload')->define('assertions_are_loaded_before_the_first_test', function (): void {
    $wasLoaded = class_exists(Assert::class, false);

    Assert::isTrue($wasLoaded);
});
