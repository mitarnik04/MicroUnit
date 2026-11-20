<?php

use MicroUnit\Assertion\Assert;
use MicroUnit\Exceptions\TestFailedException;
use MicroUnit\Setup\TestSetup;
use MicroUnit\Tests\Fixtures\AssertionCounter;

$tester = TestSetup::getTester('CollectionAssertions');

$tester->define('each_passes_when_every_element_passes', function (): void {
    Assert::each([2, 4, 6], fn(int $number) => Assert::exact(0, $number % 2));
    Assert::each(['a' => 1, 'b' => 2], fn(int $value, string $key) => $value > 0 && $key !== '');
});

$tester->define('each_reports_every_failing_key', function (): void {
    $failure = Assert::throws(
        fn() => Assert::each([1, 2, 3, 4], fn(int $number) => Assert::exact(0, $number % 2)),
        TestFailedException::class,
    )->failure;

    Assert::stringContains('2 of 4 failed', $failure->message);
    Assert::stringContains('[0]', $failure->message);
    Assert::stringContains('[2]', $failure->message);
    Assert::exact([0 => 1, 2 => 3], $failure->actual);
});

$tester->define('each_treats_false_as_failure', function (): void {
    Assert::throws(fn() => Assert::each(['x'], fn() => false), TestFailedException::class, 'returned false');
});

$tester->define('contains_all_ignores_order_and_extra_elements', function (): void {
    Assert::containsAll([3, 1], [1, 2, 3]);
    Assert::throws(fn() => Assert::containsAll([1, 4], [1, 2, 3]), TestFailedException::class, 'missing: 1');
});

$tester->define('is_unique_lists_the_duplicates', function (): void {
    Assert::isUnique([1, '1', 2.0, 2, [1], [2]]);
    $failure = Assert::throws(fn() => Assert::isUnique(['a', 'b', 'a', [1], [1]]), TestFailedException::class)->failure;

    Assert::exact(['a', [1]], $failure->actual);
});

$tester->define('is_sorted_ascending_descending_and_by_key', function (): void {
    Assert::isSorted([1, 2, 2, 5]);
    Assert::isSorted(['c', 'b', 'a'], descending: true);
    Assert::isSorted([['age' => 3], ['age' => 9]], by: fn(array $person) => $person['age']);
});

$tester->define('is_sorted_names_the_first_element_out_of_order', function (): void {
    Assert::throws(fn() => Assert::isSorted([1, 3, 2, 4]), TestFailedException::class, 'element 2 is out of order');
});

$tester->define('is_list', function (): void {
    Assert::isList([]);
    Assert::isList(['a', 'b']);
    Assert::throws(fn() => Assert::isList([1 => 'a']), TestFailedException::class);
});

$tester->define('same_size', function (): void {
    Assert::sameSize([1, 2], new ArrayObject(['a', 'b']));
    Assert::throws(fn() => Assert::sameSize([1], [1, 2]), TestFailedException::class);
});

$tester->define('a_passing_is_sorted_counts_as_an_assertion', function (): void {
    Assert::exact(1, AssertionCounter::countedBy(fn() => Assert::isSorted([1, 2, 3])));
});

$tester->define('contains_all_reads_a_generator_only_once', function (): void {
    $numbers = (function (): Generator {
        yield 1;
        yield 2;
    })();

    Assert::containsAll([2, 1], $numbers);
});

$tester->define('is_unique_compares_arrays_strictly', function (): void {
    $first = new stdClass();
    $second = new stdClass();

    Assert::isUnique([[$first], [$second]]);
    Assert::isUnique([[fn() => 1], [fn() => 2]]);
    Assert::throws(fn() => Assert::isUnique([[$first], [$first]]), TestFailedException::class, 'duplicated: 1');
});
