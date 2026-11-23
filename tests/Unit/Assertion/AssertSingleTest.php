<?php

use MicroUnit\Assertion\Assert;
use MicroUnit\Assertion\AssertSingle;
use MicroUnit\Exceptions\TestFailedException;
use MicroUnit\Setup\TestSetup;
use MicroUnit\Tests\Fixtures\AssertionCounter;

$tester = TestSetup::getTester('AssertSingle');

$tester->define('checks_can_be_chained_and_each_counts', function (): void {
    $counted = AssertionCounter::countedBy(fn() => AssertSingle::begin(42)->notNull()->isType('int')->exact(42));

    Assert::exact(3, $counted);
});

$tester->defineGroup('passes_when_the_value_matches', function (Closure $check): void {
    $check();
}, [
    'equals' => fn() => AssertSingle::begin(1)->equals('1'),
    'notEquals' => fn() => AssertSingle::begin(1)->notEquals(2),
    'exact' => fn() => AssertSingle::begin('a')->exact('a'),
    'notExact' => fn() => AssertSingle::begin(1)->notExact('1'),
    'instanceOf' => fn() => AssertSingle::begin(new ArrayObject())->instanceOf(Countable::class),
    'isTrue' => fn() => AssertSingle::begin(true)->isTrue(),
    'isFalse' => fn() => AssertSingle::begin(false)->isFalse(),
    'isNull' => fn() => AssertSingle::begin(null)->isNull(),
    'notNull' => fn() => AssertSingle::begin(0)->notNull(),
    'isType' => fn() => AssertSingle::begin(1.5)->isType('int|float'),
    'equivalent' => fn() => AssertSingle::begin([3, 1, 2])->equivalent([1, 2, 3]),
    'hasProperty' => fn() => AssertSingle::begin((object) ['id' => 1])->hasProperty('id'),
    'dateEquals' => fn() => AssertSingle::begin(new DateTimeImmutable('2026-01-01 12:00:00 UTC'))->dateEquals('2026-01-01 13:00:00 +01:00'),
]);

$tester->defineGroup('fails_when_the_value_does_not_match', function (Closure $check): void {
    Assert::throws($check, TestFailedException::class);
}, [
    'equals' => fn() => AssertSingle::begin(1)->equals(2),
    'notEquals' => fn() => AssertSingle::begin(1)->notEquals('1'),
    'exact' => fn() => AssertSingle::begin(1)->exact('1'),
    'notExact' => fn() => AssertSingle::begin('a')->notExact('a'),
    'instanceOf' => fn() => AssertSingle::begin(new stdClass())->instanceOf(Countable::class),
    'isTrue' => fn() => AssertSingle::begin(1)->isTrue(),
    'isFalse' => fn() => AssertSingle::begin(0)->isFalse(),
    'isNull' => fn() => AssertSingle::begin(0)->isNull(),
    'notNull' => fn() => AssertSingle::begin(null)->notNull(),
    'isType' => fn() => AssertSingle::begin('1')->isType('int'),
    'equivalent' => fn() => AssertSingle::begin([1, 2])->equivalent([1, 2, 3]),
    'hasProperty' => fn() => AssertSingle::begin((object) ['id' => 1])->hasProperty('name'),
    'dateEquals' => fn() => AssertSingle::begin('2026-01-01')->dateEquals('2026-01-02'),
]);

$tester->define('has_property_on_a_value_that_is_not_an_object_fails_with_its_type', function (): void {
    Assert::throws(fn() => AssertSingle::begin(['id' => 1])->hasProperty('id'), TestFailedException::class, 'of type object');
});

$tester->define('date_equals_on_a_value_that_is_not_a_date_fails_with_its_type', function (): void {
    Assert::throws(fn() => AssertSingle::begin(1767225600)->dateEquals('2026-01-01'), TestFailedException::class, 'of type DateTimeInterface|string');
});

$tester->define('the_custom_message_is_part_of_the_failure', function (): void {
    Assert::throws(fn() => AssertSingle::begin(null)->notNull('The user needs an id'), TestFailedException::class, 'The user needs an id');
});
