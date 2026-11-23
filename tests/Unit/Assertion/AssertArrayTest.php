<?php

use MicroUnit\Assertion\Assert;
use MicroUnit\Assertion\AssertArray;
use MicroUnit\Exceptions\TestFailedException;
use MicroUnit\Setup\TestSetup;
use MicroUnit\Tests\Fixtures\AssertionCounter;

$tester = TestSetup::getTester('AssertArray');

$tester->define('checks_can_be_chained_and_each_counts', function (): void {
    $counted = AssertionCounter::countedBy(fn() => AssertArray::begin(['id' => 1, 'name' => 'Ada'])->hasKey('id')->notHasKey('password')->countEquals(2));

    Assert::exact(3, $counted);
});

$tester->defineGroup('passes_when_the_array_matches', function (Closure $check): void {
    $check();
}, [
    'equals' => fn() => AssertArray::begin(['a' => 1])->equals(['a' => '1']),
    'notEquals' => fn() => AssertArray::begin([1])->notEquals([2]),
    'exact' => fn() => AssertArray::begin([1, 2])->exact([1, 2]),
    'notExact' => fn() => AssertArray::begin([1])->notExact(['1']),
    'empty' => fn() => AssertArray::begin([])->empty(),
    'notEmpty' => fn() => AssertArray::begin([0])->notEmpty(),
    'contains' => fn() => AssertArray::begin([1, 2])->contains(2),
    'notContains' => fn() => AssertArray::begin([1, 2])->notContains('2'),
    'countEquals' => fn() => AssertArray::begin([1, 2])->countEquals(2),
    'hasKey' => fn() => AssertArray::begin(['id' => 1])->hasKey('id'),
    'notHasKey' => fn() => AssertArray::begin(['id' => 1])->notHasKey('password'),
    'keysEqual' => fn() => AssertArray::begin(['id' => 1, 'name' => 'Ada'])->keysEqual(['id', 'name']),
    'containsOnly' => fn() => AssertArray::begin(['a', 'b', 'a'])->containsOnly(['a', 'b']),
    'equivalent' => fn() => AssertArray::begin([3, 1, 2])->equivalent([1, 2, 3]),
    'containsAll' => fn() => AssertArray::begin([1, 2, 3])->containsAll([3, 1]),
    'containsSubset' => fn() => AssertArray::begin(['id' => 1, 'meta' => ['at' => 'now', 'by' => 'Ada']])->containsSubset(['meta' => ['by' => 'Ada']]),
    'isList' => fn() => AssertArray::begin(['a', 'b'])->isList(),
    'isUnique' => fn() => AssertArray::begin([1, '1'])->isUnique(),
    'isSorted' => fn() => AssertArray::begin([1, 2, 2, 5])->isSorted(),
    'isSorted by a key, descending' => fn() => AssertArray::begin([['n' => 3], ['n' => 1]])->isSorted(by: fn(array $row) => $row['n'], descending: true),
    'each' => fn() => AssertArray::begin([2, 4])->each(fn(int $number) => $number % 2 === 0),
    'sameSize' => fn() => AssertArray::begin([1, 2])->sameSize(new ArrayObject(['a', 'b'])),
]);

$tester->defineGroup('fails_when_the_array_does_not_match', function (Closure $check): void {
    Assert::throws($check, TestFailedException::class);
}, [
    'equals' => fn() => AssertArray::begin([1])->equals([2]),
    'notEquals' => fn() => AssertArray::begin([1])->notEquals(['1']),
    'exact' => fn() => AssertArray::begin([1])->exact(['1']),
    'notExact' => fn() => AssertArray::begin([1])->notExact([1]),
    'empty' => fn() => AssertArray::begin([0])->empty(),
    'notEmpty' => fn() => AssertArray::begin([])->notEmpty(),
    'contains' => fn() => AssertArray::begin([1, 2])->contains('2'),
    'notContains' => fn() => AssertArray::begin([1, 2])->notContains(2),
    'countEquals' => fn() => AssertArray::begin([1, 2])->countEquals(3),
    'hasKey' => fn() => AssertArray::begin(['id' => 1])->hasKey('name'),
    'notHasKey' => fn() => AssertArray::begin(['password' => 'x'])->notHasKey('password'),
    'keysEqual' => fn() => AssertArray::begin(['id' => 1])->keysEqual(['id', 'name']),
    'containsOnly' => fn() => AssertArray::begin(['a', 'c'])->containsOnly(['a', 'b']),
    'equivalent' => fn() => AssertArray::begin([1, 2])->equivalent([1, 2, 2]),
    'containsAll' => fn() => AssertArray::begin([1, 2])->containsAll([3]),
    'containsSubset' => fn() => AssertArray::begin(['meta' => ['by' => 'Ada']])->containsSubset(['meta' => ['by' => 'Bob']]),
    'isList' => fn() => AssertArray::begin([1 => 'a'])->isList(),
    'isUnique' => fn() => AssertArray::begin([1, 1])->isUnique(),
    'isSorted' => fn() => AssertArray::begin([2, 1])->isSorted(),
    'each' => fn() => AssertArray::begin([2, 3])->each(fn(int $number) => $number % 2 === 0),
    'sameSize' => fn() => AssertArray::begin([1, 2])->sameSize([1]),
]);
