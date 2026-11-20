<?php

namespace MicroUnit\Setup;

use MicroUnit\Core\Tester;
use MicroUnit\Core\TestRegistry;

/** Entry point for test files. */
final class TestSetup
{
    private function __construct() {}

    /** Returns the Tester of $suite; every file that asks for the same suite name gets the same Tester. */
    public static function getTester(string $suite): Tester
    {
        return TestRegistry::instance()->tester($suite);
    }
}
