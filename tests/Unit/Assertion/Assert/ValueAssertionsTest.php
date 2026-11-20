<?php

use MicroUnit\Assertion\Assert;
use MicroUnit\Exceptions\TestFailedException;
use MicroUnit\Setup\TestSetup;

$tester = TestSetup::getTester('ValueAssertions');

$tester->define('is_type_accepts_builtin_union_nullable_and_class_types', function (): void {
    Assert::isType('int', 1);
    Assert::isType('int|string', 'a');
    Assert::isType('?float', null);
    Assert::isType('list', [1, 2]);
    Assert::isType('numeric', '4.2');
    Assert::isType('callable', 'strlen');
    Assert::isType(DateTimeInterface::class, new DateTimeImmutable());
});

$tester->define('is_type_fails_with_the_actual_type', function (): void {
    $failure = Assert::throws(fn() => Assert::isType('int|float', '3'), TestFailedException::class)->failure;

    Assert::exact('string', $failure->actual);
});

$tester->define('is_type_rejects_a_map_as_list', function (): void {
    Assert::throws(fn() => Assert::isType('list', ['a' => 1]), TestFailedException::class);
});

$tester->define('is_type_reports_unknown_types', function (): void {
    Assert::throws(fn() => Assert::isType('integer', 1), InvalidArgumentException::class, "Unknown type 'integer'");
});

$tester->define('equivalent_ignores_list_order_and_key_order_recursively', function (): void {
    Assert::equivalent(
        ['tags' => ['b', 'a'], 'id' => 1, 'items' => [['x' => 2], ['x' => 1]]],
        ['id' => 1, 'items' => [['x' => 1], ['x' => 2]], 'tags' => ['a', 'b']],
    );
});

$tester->define('equivalent_still_counts_duplicates', function (): void {
    Assert::throws(fn() => Assert::equivalent([1, 1, 2], [1, 2, 2]), TestFailedException::class);
});

$tester->define('contains_subset_ignores_extra_keys', function (): void {
    Assert::containsSubset(
        ['user' => ['name' => 'Ada'], 'status' => 'active'],
        ['id' => 7, 'status' => 'active', 'user' => ['name' => 'Ada', 'email' => 'ada@example.com']],
    );
});

$tester->define('contains_subset_reads_decoded_json_objects_and_array_access', function (): void {
    Assert::containsSubset(['data' => ['id' => 3]], json_decode('{"data":{"id":3,"name":"x"},"meta":{}}'));
    Assert::containsSubset(['a' => 1], new ArrayObject(['a' => 1, 'b' => 2]));
});

$tester->define('contains_subset_names_missing_keys_by_path', function (): void {
    $failure = Assert::throws(
        fn() => Assert::containsSubset(['user' => ['email' => 'x', 'name' => 'Ada']], ['user' => ['name' => 'Ada']]),
        TestFailedException::class,
    )->failure;

    Assert::stringContains('missing: user.email', $failure->message);
});

$tester->define('contains_subset_diffs_only_the_relevant_part', function (): void {
    $failure = Assert::throws(
        fn() => Assert::containsSubset(['status' => 'active'], ['status' => 'banned', 'noise' => str_repeat('x', 50)]),
        TestFailedException::class,
    )->failure;

    Assert::exact(['status' => 'banned'], $failure->actual);
});

$tester->define('equals_compares_objects_property_by_property', function (): void {
    Assert::equals(new DateInterval('P1D'), new DateInterval('P1D')); // PHP's == refuses DateInterval
    Assert::equals(['interval' => new DateInterval('PT1H')], ['interval' => new DateInterval('PT1H')]);
    Assert::equals(new DateTimeImmutable('2026-01-01 00:00 UTC'), new DateTimeImmutable('2026-01-01 01:00 +01:00'));
    Assert::throws(fn() => Assert::equals(new DateInterval('P1D'), new DateInterval('P2D')), TestFailedException::class);
    Assert::throws(fn() => Assert::equals(new ArrayObject([1]), new ArrayIterator([1])), TestFailedException::class);
});

$tester->define('has_property', function (): void {
    Assert::hasProperty('format', new class () {
        public string $format = 'Y';
    });
    Assert::throws(fn() => Assert::hasProperty('missing', new stdClass()), TestFailedException::class);
});

$tester->define('equals_compares_object_graphs_with_cycles', function (): void {
    $family = static function (string $childName): stdClass {
        $parent = new stdClass();
        $parent->child = new stdClass();
        $parent->child->name = $childName;
        $parent->child->parent = $parent;

        return $parent;
    };

    Assert::equals($family('Ada'), $family('Ada'));
    Assert::throws(fn() => Assert::equals($family('Ada'), $family('Bob')), TestFailedException::class);
});

$tester->define('equivalent_compares_values_like_equals_even_when_their_types_sort_apart', function (): void {
    Assert::equivalent([5, 10], ['5', 10]);
    Assert::equivalent([[10, 5]], [['5', '10']]);
    Assert::throws(fn() => Assert::equivalent([5, 10], ['5', 11]), TestFailedException::class);
});
