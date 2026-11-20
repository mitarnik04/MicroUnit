<?php

use MicroUnit\Assertion\Assert;
use MicroUnit\Core\TestCase;
use MicroUnit\Exceptions\TestFailedException;
use MicroUnit\Exceptions\TestSkippedException;
use MicroUnit\Setup\TestSetup;

$tester = TestSetup::getTester('Assert');

$tester->defineGroup('passes', function (Closure $assertion): void {
    $assertion();
}, [
    new TestCase('equals', [fn() => Assert::equals(2, 1 + 1)]),
    new TestCase('equals_loosely', [fn() => Assert::equals('2', 2)]),
    new TestCase('not_equals', [fn() => Assert::notEquals(3, 1 + 1)]),
    new TestCase('exact', [fn() => Assert::exact('a', 'a')]),
    new TestCase('not_exact', [fn() => Assert::notExact('2', 2)]),
    new TestCase('is_true', [fn() => Assert::isTrue(true)]),
    new TestCase('is_false', [fn() => Assert::isFalse(false)]),
    new TestCase('is_null', [fn() => Assert::isNull(null)]),
    new TestCase('not_null', [fn() => Assert::notNull(0)]),
    new TestCase('instance_of', [fn() => Assert::instanceOf(DateTimeInterface::class, new DateTimeImmutable())]),
    new TestCase('is_greater_than', [fn() => Assert::isGreaterThan(1, 2)]),
    new TestCase('is_greater_than_or_equal', [fn() => Assert::isGreaterThanOrEqual(2, 2)]),
    new TestCase('is_less_than', [fn() => Assert::isLessThan(2, 1)]),
    new TestCase('is_less_than_or_equal', [fn() => Assert::isLessThanOrEqual(2, 2)]),
    new TestCase('is_between_inclusive', [fn() => Assert::isBetween(1, 3, 3)]),
    new TestCase('is_between_exclusive', [fn() => Assert::isBetween(1, 3, 2, inclusive: false)]),
    new TestCase('equals_with_delta', [fn() => Assert::equalsWithDelta(0.3, 0.1 + 0.2, 0.0001)]),
    new TestCase('empty_array', [fn() => Assert::empty([])]),
    new TestCase('empty_string', [fn() => Assert::empty('')]),
    new TestCase('not_empty', [fn() => Assert::notEmpty([1])]),
    new TestCase('contains_strictly', [fn() => Assert::contains(2, [1, 2, 3])]),
    new TestCase('contains_in_an_iterator', [fn() => Assert::contains('b', new ArrayIterator(['a', 'b']))]),
    new TestCase('not_contains', [fn() => Assert::notContains('2', [1, 2, 3])]),
    new TestCase('count_equals', [fn() => Assert::countEquals(2, [1, 2])]),
    new TestCase('has_key', [fn() => Assert::hasKey('id', ['id' => 1])]),
    new TestCase('not_has_key', [fn() => Assert::notHasKey('password', ['id' => 1])]),
    new TestCase('keys_equal_in_any_order', [fn() => Assert::keysEqual(['b', 'a'], ['a' => 1, 'b' => 2])]),
    new TestCase('contains_only', [fn() => Assert::containsOnly([1, 2], [2, 1, 2])]),
    new TestCase('string_contains', [fn() => Assert::stringContains('nit', 'microunit')]),
    new TestCase('string_not_contains', [fn() => Assert::stringNotContains('php', 'microunit')]),
    new TestCase('string_starts_with', [fn() => Assert::stringStartsWith('micro', 'microunit')]),
    new TestCase('string_ends_with', [fn() => Assert::stringEndsWith('unit', 'microunit')]),
    new TestCase('matches_regex', [fn() => Assert::matchesRegex('/^\d{3}$/', '123')]),
]);

$tester->defineGroup('fails', function (Closure $assertion): void {
    Assert::throws($assertion, TestFailedException::class);
}, [
    new TestCase('equals', [fn() => Assert::equals(2, 3)]),
    new TestCase('not_equals', [fn() => Assert::notEquals(2, 2)]),
    new TestCase('exact_with_different_types', [fn() => Assert::exact(1, '1')]),
    new TestCase('not_exact', [fn() => Assert::notExact(1, 1)]),
    new TestCase('is_true_with_a_truthy_value', [fn() => Assert::isTrue(1)]),
    new TestCase('is_false_with_a_falsy_value', [fn() => Assert::isFalse(0)]),
    new TestCase('is_null', [fn() => Assert::isNull(false)]),
    new TestCase('not_null', [fn() => Assert::notNull(null)]),
    new TestCase('instance_of_with_a_scalar', [fn() => Assert::instanceOf(DateTimeInterface::class, 'now')]),
    new TestCase('is_greater_than', [fn() => Assert::isGreaterThan(2, 2)]),
    new TestCase('is_less_than', [fn() => Assert::isLessThan(2, 2)]),
    new TestCase('is_between', [fn() => Assert::isBetween(1, 3, 4)]),
    new TestCase('is_between_exclusive_bound', [fn() => Assert::isBetween(1, 3, 3, inclusive: false)]),
    new TestCase('equals_with_delta', [fn() => Assert::equalsWithDelta(1.0, 1.1, 0.05)]),
    new TestCase('empty', [fn() => Assert::empty([1])]),
    new TestCase('not_empty', [fn() => Assert::notEmpty('')]),
    new TestCase('contains_is_strict_by_default', [fn() => Assert::contains('2', [1, 2, 3])]),
    new TestCase('count_equals', [fn() => Assert::countEquals(3, [1, 2])]),
    new TestCase('has_key', [fn() => Assert::hasKey('id', [])]),
    new TestCase('keys_equal', [fn() => Assert::keysEqual(['a'], ['a' => 1, 'b' => 2])]),
    new TestCase('contains_only', [fn() => Assert::containsOnly([1], [1, 2])]),
    new TestCase('string_contains', [fn() => Assert::stringContains('php', 'microunit')]),
    new TestCase('matches_regex', [fn() => Assert::matchesRegex('/^\d+$/', 'abc')]),
    new TestCase('fail', [fn() => Assert::fail('stop')]),
]);

$tester->define('expected_comes_first_and_actual_last', function (): void {
    $failure = Assert::throws(fn() => Assert::equals('expected', 'actual'), TestFailedException::class)->failure;

    Assert::exact('expected', $failure->expected);
    Assert::exact('actual', $failure->actual);
});

$tester->define('numeric_comparisons_take_the_bound_first_like_phpunit', function (): void {
    Assert::isGreaterThan(10, 11);
    Assert::throws(fn() => Assert::isGreaterThan(11, 10), TestFailedException::class);
});

$tester->define('custom_message_comes_before_the_description', function (): void {
    $exception = Assert::throws(fn() => Assert::isTrue(false, 'the user should be active'), TestFailedException::class);

    Assert::stringStartsWith('the user should be active', $exception->getMessage());
    Assert::exact('the user should be active', $exception->failure->customMessage);
});

$tester->define('equality_failures_carry_a_diff', function (): void {
    $failure = Assert::throws(fn() => Assert::equals([1, 2], [1, 3]), TestFailedException::class)->failure;

    Assert::isTrue($failure->diff?->hasChanges());
});

$tester->define('null_counts_as_an_expected_value', function (): void {
    $failure = Assert::throws(fn() => Assert::isNull(0), TestFailedException::class)->failure;

    Assert::isTrue($failure->hasExpected());
    Assert::isNull($failure->expected);
});

$tester->define('throws_returns_the_caught_exception', function (): void {
    $exception = Assert::throws(fn() => throw new RuntimeException('boom'), RuntimeException::class);

    Assert::exact('boom', $exception->getMessage());
});

$tester->define('throws_checks_the_message_substring', function (): void {
    Assert::throws(fn() => throw new LogicException('user not found'), LogicException::class, 'not found');
    Assert::throws(
        fn() => Assert::throws(fn() => throw new LogicException('user not found'), LogicException::class, 'banned'),
        TestFailedException::class,
    );
});

$tester->define('throws_fails_on_the_wrong_exception_type', function (): void {
    Assert::throws(
        fn() => Assert::throws(fn() => throw new RuntimeException(), InvalidArgumentException::class),
        TestFailedException::class,
    );
});

$tester->define('throws_fails_when_nothing_is_thrown', function (): void {
    Assert::throws(fn() => Assert::throws(fn() => 42), TestFailedException::class);
});

$tester->define('skip_throws_with_the_reason', function (): void {
    $exception = Assert::throws(fn() => Assert::skip('database not available'), TestSkippedException::class);

    Assert::exact('database not available', $exception->getMessage());
});

$tester->define('skip_if_only_skips_when_the_condition_holds', function (): void {
    Assert::skipIf(false, 'never');
    Assert::throws(fn() => Assert::skipIf(true, 'always'), TestSkippedException::class, 'always');
});
