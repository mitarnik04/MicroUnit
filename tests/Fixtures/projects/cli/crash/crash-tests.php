<?php

use MicroUnit\Assertion\Assert;
use MicroUnit\Setup\TestSetup;

TestSetup::getTester('Survivor')->define('passes', fn() => Assert::isTrue(true));
TestSetup::getTester('Crash')->define('exits_the_worker', function (): void {
    exit(3);
});
