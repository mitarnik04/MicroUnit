<?php

use MicroUnit\Assertion\Assert;
use MicroUnit\Setup\TestSetup;

// The outcome of every test here is known; bin/canary.php checks that MicroUnit reports exactly that.
$tester = TestSetup::getTester('Canary');

$tester->define('passes', function (): void {
    Assert::exact(4, 2 + 2);
});

$tester->define('a_failing_assertion_fails', function (): void {
    Assert::exact(5, 2 + 2);
});

$tester->define('an_expected_exception_that_is_never_thrown_fails', function (): void {
    Assert::throws(fn() => null, RuntimeException::class);
});

$tester->define('an_unexpected_exception_fails', function (): void {
    throw new RuntimeException('Thrown on purpose by the canary.');
});
