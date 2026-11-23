<?php

use MicroUnit\Assertion\Assert;
use MicroUnit\Assertion\AssertNumeric;
use MicroUnit\Exceptions\TestFailedException;
use MicroUnit\Setup\TestSetup;
use MicroUnit\Tests\Fixtures\AssertionCounter;

$tester = TestSetup::getTester('AssertNumeric');

$tester->define('checks_can_be_chained_and_each_counts', function (): void {
    $counted = AssertionCounter::countedBy(fn() => AssertNumeric::begin(50)->isFinite()->isGreaterThan(0)->isLessThanOrEqual(100));

    Assert::exact(3, $counted);
});

$tester->defineGroup('passes_when_the_number_matches', function (Closure $check): void {
    $check();
}, [
    'equals' => fn() => AssertNumeric::begin(1)->equals(1.0),
    'notEquals' => fn() => AssertNumeric::begin(1)->notEquals(2),
    'exact' => fn() => AssertNumeric::begin(1)->exact(1),
    'notExact' => fn() => AssertNumeric::begin(1)->notExact(1.0),
    'equalsWithDelta' => fn() => AssertNumeric::begin(0.1 + 0.2)->equalsWithDelta(0.3, 0.0001),
    'equalsWithinPercent' => fn() => AssertNumeric::begin(104)->equalsWithinPercent(100, 5),
    'isGreaterThan' => fn() => AssertNumeric::begin(2)->isGreaterThan(1),
    'isGreaterThanOrEqual' => fn() => AssertNumeric::begin(1)->isGreaterThanOrEqual(1),
    'isLessThan' => fn() => AssertNumeric::begin(1)->isLessThan(2),
    'isLessThanOrEqual' => fn() => AssertNumeric::begin(2)->isLessThanOrEqual(2),
    'isBetween' => fn() => AssertNumeric::begin(5)->isBetween(1, 10),
    'isNan' => fn() => AssertNumeric::begin(NAN)->isNan(),
    'isFinite' => fn() => AssertNumeric::begin(1e10)->isFinite(),
]);

$tester->defineGroup('fails_when_the_number_does_not_match', function (Closure $check): void {
    Assert::throws($check, TestFailedException::class);
}, [
    'equals' => fn() => AssertNumeric::begin(1)->equals(2),
    'notEquals' => fn() => AssertNumeric::begin(1)->notEquals(1.0),
    'exact' => fn() => AssertNumeric::begin(1)->exact(1.0),
    'notExact' => fn() => AssertNumeric::begin(1)->notExact(1),
    'equalsWithDelta' => fn() => AssertNumeric::begin(0.4)->equalsWithDelta(0.3, 0.0001),
    'equalsWithinPercent' => fn() => AssertNumeric::begin(106)->equalsWithinPercent(100, 5),
    'isGreaterThan' => fn() => AssertNumeric::begin(1)->isGreaterThan(1),
    'isGreaterThanOrEqual' => fn() => AssertNumeric::begin(0)->isGreaterThanOrEqual(1),
    'isLessThan' => fn() => AssertNumeric::begin(2)->isLessThan(2),
    'isLessThanOrEqual' => fn() => AssertNumeric::begin(3)->isLessThanOrEqual(2),
    'isBetween' => fn() => AssertNumeric::begin(11)->isBetween(1, 10),
    'isNan' => fn() => AssertNumeric::begin(1.0)->isNan(),
    'isFinite' => fn() => AssertNumeric::begin(INF)->isFinite(),
]);
