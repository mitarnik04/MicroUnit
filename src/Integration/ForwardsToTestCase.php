<?php

namespace MicroUnit\Integration;

use MicroUnit\Core\Tester;

/**
 * What the static proxies (Laravel, Symfony, CodeIgniter) share: every unknown static call goes to
 * the framework test case of the running test, and a suite can choose its traits and base test case.
 */
trait ForwardsToTestCase
{
    private function __construct() {}

    /**
     * Gives every test of the suite a trait, like a `use` statement in a PHPUnit test class
     * (RefreshDatabase, DatabaseTestTrait, ...).
     *
     * @param class-string ...$traits
     */
    public static function uses(Tester $tester, string ...$traits): void
    {
        HostedTestCaseIntegration::useTraitsFor($tester, ...$traits);
    }

    /**
     * Runs every test of the suite in this base test case instead of the configured one, like
     * `extends` in a PHPUnit test class.
     *
     * @param class-string $testCase
     */
    public static function useTestCase(Tester $tester, string $testCase): void
    {
        HostedTestCaseIntegration::useTestCaseFor($tester, $testCase);
    }

    /** @param list<mixed> $arguments */
    public static function __callStatic(string $method, array $arguments): mixed
    {
        return HostedTestCaseIntegration::forward($method, $arguments);
    }
}
