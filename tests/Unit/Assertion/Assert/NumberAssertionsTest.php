<?php

use MicroUnit\Assertion\Assert;
use MicroUnit\Exceptions\TestFailedException;
use MicroUnit\Setup\TestSetup;

$tester = TestSetup::getTester('NumberAssertions');

$tester->define('equals_within_percent_accepts_the_tolerance_band', function (): void {
    Assert::equalsWithinPercent(100, 95, 5);
    Assert::equalsWithinPercent(100, 105, 5);
    Assert::equalsWithinPercent(-200, -210, 5);
    Assert::throws(fn() => Assert::equalsWithinPercent(100, 106, 5), TestFailedException::class, 'within 5%');
});

$tester->define('is_nan_and_is_finite', function (): void {
    Assert::isNan(NAN);
    Assert::isFinite(1.5);
    Assert::throws(fn() => Assert::isNan(1.0), TestFailedException::class);
    Assert::throws(fn() => Assert::isFinite(INF), TestFailedException::class);
});

$tester->define('date_equals_with_tolerance', function (): void {
    $moment = new DateTimeImmutable('2026-01-01 12:00:00');

    Assert::dateEquals('2026-01-01 12:00:00', $moment);
    Assert::dateEquals($moment, $moment->modify('+3 seconds'), toleranceInSeconds: 5);
});

$tester->define('date_equals_reports_the_difference', function (): void {
    $moment = new DateTimeImmutable('2026-01-01 12:00:00');

    Assert::throws(fn() => Assert::dateEquals($moment, $moment->modify('+90 seconds'), 60), TestFailedException::class, 'differ by 90 s');
});
