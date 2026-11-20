<?php

namespace MicroUnit\Assertion\Assert;

use MicroUnit\Assertion\AssertionFailure;
use MicroUnit\Helpers\ValueExporter;

/** Arrays, iterables and Countables. Part of Assert. */
trait CollectionAssertions
{
    private const int LISTED_ELEMENT_FAILURES = 10;

    /** Arrays and Countables without elements, '', null, 0, false. */
    public static function empty(mixed $actual, string $message = ''): void
    {
        self::check(self::isEmptyValue($actual), 'Failed asserting that value is empty.', actual: $actual, customMessage: $message);
    }

    public static function notEmpty(mixed $actual, string $message = ''): void
    {
        self::check(!self::isEmptyValue($actual), 'Failed asserting that value is not empty.', actual: $actual, customMessage: $message);
    }

    /** @param iterable<mixed> $haystack */
    public static function contains(mixed $needle, iterable $haystack, bool $strict = true, string $message = ''): void
    {
        self::check(self::iterableContains($haystack, $needle, $strict), 'Failed asserting that the collection contains the expected element.', $needle, $haystack, customMessage: $message);
    }

    /** @param iterable<mixed> $haystack */
    public static function notContains(mixed $needle, iterable $haystack, bool $strict = true, string $message = ''): void
    {
        self::check(!self::iterableContains($haystack, $needle, $strict), 'Failed asserting that the collection does not contain the element.', $needle, $haystack, customMessage: $message);
    }

    /**
     * Asserts that every element of $expected is in $actual, in any order; other elements are allowed.
     *
     * @param array<mixed> $expected
     * @param iterable<mixed> $actual
     */
    public static function containsAll(array $expected, iterable $actual, bool $strict = true, string $message = ''): void
    {
        // Searched once per expected element, so a generator (which runs only once) is read up front.
        $elements = is_array($actual) ? $actual : iterator_to_array($actual, false);
        $missing = array_values(array_filter($expected, static fn(mixed $element): bool => !in_array($element, $elements, $strict)));
        if (!self::passes($missing === [])) {
            self::reject('Failed asserting that the collection contains all expected elements; missing: ' . count($missing) . '.', $missing, $elements, customMessage: $message);
        }
    }

    /**
     * Asserts that every element of $actual is one of $allowedValues (strict comparison).
     *
     * @param array<mixed> $allowedValues
     * @param array<mixed> $actual
     */
    public static function containsOnly(array $allowedValues, array $actual, string $message = ''): void
    {
        $disallowed = array_values(array_filter($actual, static fn(mixed $item): bool => !in_array($item, $allowedValues, true)));

        self::check($disallowed === [], 'Failed asserting that the array contains only allowed values.', $allowedValues, $disallowed, customMessage: $message);
    }

    /** @param array<mixed>|\Countable $actual */
    public static function countEquals(int $expected, array|\Countable $actual, string $message = ''): void
    {
        $actualCount = count($actual);
        if (!self::passes($actualCount === $expected)) {
            self::reject("Failed asserting that the collection has $expected element(s).", $expected, $actualCount, customMessage: $message);
        }
    }

    /**
     * @param array<mixed>|\Countable $expected
     * @param array<mixed>|\Countable $actual
     */
    public static function sameSize(array|\Countable $expected, array|\Countable $actual, string $message = ''): void
    {
        $expectedCount = count($expected);
        $actualCount = count($actual);
        self::check($expectedCount === $actualCount, 'Failed asserting that both collections have the same size.', $expectedCount, $actualCount, customMessage: $message);
    }

    /** @param array<mixed> $actual */
    public static function hasKey(int|string $key, array $actual, string $message = ''): void
    {
        if (!self::passes(array_key_exists($key, $actual))) {
            self::reject("Failed asserting that the array has the key '$key'.", $key, array_keys($actual), customMessage: $message);
        }
    }

    /** @param array<mixed> $actual */
    public static function notHasKey(int|string $key, array $actual, string $message = ''): void
    {
        if (!self::passes(!array_key_exists($key, $actual))) {
            self::reject("Failed asserting that the array does not have the key '$key'.", actual: array_keys($actual), customMessage: $message);
        }
    }

    /**
     * Asserts that the array has exactly the given keys, in any order.
     *
     * @param list<int|string> $expectedKeys
     * @param array<mixed> $actual
     */
    public static function keysEqual(array $expectedKeys, array $actual, string $message = ''): void
    {
        $actualKeys = array_keys($actual);
        sort($expectedKeys);
        sort($actualKeys);

        self::check($expectedKeys === $actualKeys, 'Failed asserting that the array has exactly the expected keys.', $expectedKeys, $actualKeys, true, $message);
    }

    /** Keys 0, 1, 2, ... in order. */
    public static function isList(mixed $actual, string $message = ''): void
    {
        self::check(is_array($actual) && array_is_list($actual), 'Failed asserting that value is a list (keys 0, 1, 2, ...).', actual: $actual, customMessage: $message);
    }

    /**
     * Every element passes $check, which asserts (or returns false to fail). All failing elements
     * are reported with their key, not just the first.
     *
     * Assert::each($users, fn (User $user) => Assert::isTrue($user->isActive()));
     *
     * @param iterable<mixed> $items
     * @param callable(mixed $item, int|string $key): mixed $check
     */
    public static function each(iterable $items, callable $check, string $message = ''): void
    {
        $failures = [];
        $failedItems = [];
        $count = 0;
        foreach ($items as $key => $item) {
            $count++;
            [$itemFailures, $returned] = self::collectFailures($check, $item, $key);
            if ($returned === false) {
                $itemFailures[] = new AssertionFailure('The check returned false.');
            }
            if ($itemFailures !== []) {
                $failedItems[$key] = $item;
                $failures[] = "[$key] " . trim(self::describeFailures($itemFailures, '    '));
            }
        }
        if (self::passes($failures === [])) {
            return;
        }

        $listed = array_slice($failures, 0, self::LISTED_ELEMENT_FAILURES);
        $more = count($failures) - count($listed);
        self::reject(
            sprintf('Failed asserting that every element passes; %d of %d failed:', count($failures), $count) . PHP_EOL
                . '  ' . implode(PHP_EOL . '  ', $listed) . ($more > 0 ? PHP_EOL . "  ... and $more more" : ''),
            actual: $failedItems,
            customMessage: $message,
        );
    }

    /**
     * No element appears twice (strict comparison).
     *
     * @param iterable<mixed> $actual
     */
    public static function isUnique(iterable $actual, string $message = ''): void
    {
        $seen = [];
        $seenArrays = [];
        $duplicates = [];
        foreach ($actual as $element) {
            if (is_array($element)) {
                // Compared with === itself: no fingerprint keeps objects and closures inside an array apart.
                $index = array_search($element, $seenArrays, true);
                if ($index === false) {
                    $seenArrays[] = $element;
                } else {
                    $duplicates["a$index"] = $element;
                }
                continue;
            }
            $fingerprint = match (true) {
                is_int($element) => "i$element",
                is_string($element) => "s$element",
                is_object($element) => 'o' . spl_object_id($element),
                default => get_debug_type($element) . ':' . var_export($element, true),
            };
            if (isset($seen[$fingerprint])) {
                $duplicates[$fingerprint] = $element;
            }
            $seen[$fingerprint] = true;
        }
        if (!self::passes($duplicates === [])) {
            self::reject('Failed asserting that all elements are unique; duplicated: ' . count($duplicates) . '.', actual: array_values($duplicates), customMessage: $message);
        }
    }

    /**
     * Elements are in ascending (or descending) order, compared with <=>, optionally by a key.
     *
     * Assert::isSorted($posts, by: fn (Post $post) => $post->publishedAt, descending: true);
     *
     * @param iterable<mixed> $actual
     * @param (callable(mixed $element): mixed)|null $by
     */
    public static function isSorted(iterable $actual, ?callable $by = null, bool $descending = false, string $message = ''): void
    {
        $elements = is_array($actual) ? array_values($actual) : iterator_to_array($actual, false);
        $values = $by === null ? $elements : array_map($by, $elements);
        $direction = $descending ? -1 : 1;

        $outOfOrder = null;
        for ($index = 1, $count = count($values); $index < $count; $index++) {
            if (($values[$index - 1] <=> $values[$index]) * $direction > 0) {
                $outOfOrder = $index;
                break;
            }
        }
        if (self::passes($outOfOrder === null)) {
            return;
        }

        self::reject(
            sprintf('Failed asserting that the elements are sorted %s; element %d is out of order.', $descending ? 'descending' : 'ascending', $outOfOrder),
            ValueExporter::export($values[$outOfOrder - 1]) . ($descending ? ' >= ' : ' <= ') . ValueExporter::export($values[$outOfOrder]),
            $values,
            customMessage: $message,
        );
    }
}
