<?php

use MicroUnit\Assertion\Assert;
use MicroUnit\Setup\TestSetup;

TestSetup::getTester('ZeroA')->define('passes', fn() => Assert::isTrue(true));
TestSetup::getTester('ZeroB')->define('passes', fn() => Assert::isTrue(true));
