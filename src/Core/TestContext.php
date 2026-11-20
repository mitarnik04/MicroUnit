<?php

namespace MicroUnit\Core;

/** The test that is running right now: for assertions that need to know it (snapshots) and assertion counting. */
final class TestContext
{
    private static ?Test $current = null;

    /** Increases with every test that starts, so per-test state can tell two runs apart. */
    private static int $runNumber = 0;

    /** Assertions the running test has made so far. */
    private static int $assertions = 0;

    private function __construct() {}

    public static function current(): ?Test
    {
        return self::$current;
    }

    public static function runNumber(): int
    {
        return self::$runNumber;
    }

    public static function enter(Test $test): void
    {
        self::$current = $test;
        self::$runNumber++;
        self::$assertions = 0;
    }

    /**
     * Called by every assertion, passing or failing. Custom assertions call it too, so a test that
     * only uses them is not reported as passing without assertions.
     */
    public static function countAssertions(int $count = 1): void
    {
        self::$assertions += $count;
    }

    public static function assertions(): int
    {
        return self::$assertions;
    }

    public static function leave(): void
    {
        self::$current = null;
    }
}
