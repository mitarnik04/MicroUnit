<?php

use MicroUnit\Assertion\Assert;
use MicroUnit\Setup\TestSetup;

TestSetup::getTester('Configured')->define('runs_only_without_paths', fn() => Assert::isTrue(true));
