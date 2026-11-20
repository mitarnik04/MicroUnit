<?php

namespace MicroUnit\Tests\Fixtures;

use MicroUnit\Core\TestContext;

/** How many assertions a piece of test code counts for the running test. */
final class AssertionCounter
{
    private function __construct() {}

    public static function countedBy(\Closure $code): int
    {
        $before = TestContext::assertions();
        $code();

        return TestContext::assertions() - $before;
    }
}
