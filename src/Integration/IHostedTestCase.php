<?php

namespace MicroUnit\Integration;

/**
 * The framework test case TestCaseHost generates for each test: MicroUnit drives its lifecycle and
 * forwards the proxies' calls to it.
 *
 * @internal
 */
interface IHostedTestCase
{
    /** PHPUnit's class-level hooks, inherited from the framework's base test case. */
    public static function setUpBeforeClass(): void;

    public static function tearDownAfterClass(): void;

    public function setUpForMicroUnit(): void;

    public function tearDownForMicroUnit(): void;

    /** @param list<mixed> $arguments */
    public function callForMicroUnit(string $method, array $arguments): mixed;
}
