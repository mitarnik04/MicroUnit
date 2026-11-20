<?php

namespace MicroUnit\Core;

/** Hooks that run around every test of every suite (used e.g. by framework integrations). */
interface ITestLifecycle
{
    /** Runs before the suite's setUp. Throwing fails the test. */
    public function beforeTest(Test $test): void;

    /** Runs after the suite's tearDown, also when the test failed. Throwing fails a passing test. */
    public function afterTest(Test $test, TestResult $result): void;
}
