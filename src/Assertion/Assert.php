<?php

namespace MicroUnit\Assertion;

use MicroUnit\Exceptions\TestFailedException;
use MicroUnit\Assertion\AssertionFailure;
use MicroUnit\Helpers\Diff;

final class Assert
{
    //Single-Value
    public static function equals(mixed $expected, mixed $actual): void
    {
        if ($expected != $actual) {
            throw new TestFailedException(
                new AssertionFailure(
                    'Expected values to be equal',
                    $expected,
                    $actual,
                    Diff::generate($expected, $actual)
                )
            );
        }
    }

    public static function notEquals(mixed $unexpected, mixed $actual): void
    {
        if ($unexpected == $actual) {
            throw new TestFailedException(
                new AssertionFailure(
                    'Expected values to not be equal',
                    $unexpected,
                    $actual
                )
            );
        }
    }

    public static function exact(mixed $expected, mixed $actual): void
    {
        if ($expected !== $actual) {
            throw new TestFailedException(
                new AssertionFailure(
                    'Expected values to be exactly equal (type and value)',
                    $expected,
                    $actual,
                    Diff::generate($expected, $actual)
                )
            );
        }
    }

    public static function notExact(mixed $unexpected, mixed $actual): void
    {
        if ($unexpected === $actual) {
            throw new TestFailedException(
                new AssertionFailure(
                    'Expected values to not be exactly equal (type and value)',
                    $unexpected,
                    $actual
                )
            );
        }
    }

    public static function isTrue(mixed $value): void
    {
        if ($value !== true) {
            throw new TestFailedException(
                new AssertionFailure(
                    'Expected value to be TRUE',
                    true,
                    $value
                )
            );
        }
    }

    public static function isFalse(mixed $value): void
    {
        if ($value !== false) {
            throw new TestFailedException(
                new AssertionFailure(
                    'Expected value to be FALSE',
                    false,
                    $value
                )
            );
        }
    }

    public static function isNull(mixed $value): void
    {
        if ($value !== null) {
            throw new TestFailedException(
                new AssertionFailure(
                    'Expected value to be NULL',
                    null,
                    $value
                )
            );
        }
    }

    public static function notNull(mixed $value): void
    {
        if ($value === null) {
            throw new TestFailedException(
                new AssertionFailure(
                    'Expected value to be NOT NULL',
                    actual: $value
                )
            );
        }
    }

    //Numeric 
    public static function isGreaterThan(int|float $value, int|float $min): void
    {
        if ($value <= $min) {
            throw new TestFailedException(
                new AssertionFailure(
                    "Expected value to be greater than $min",
                    "> $min",
                    $value
                )
            );
        }
    }

    public static function isLessThan(int|float $value, int|float $max): void
    {
        if ($value >= $max) {
            throw new TestFailedException(
                new AssertionFailure(
                    "Expected value to be less than $max",
                    "< $max",
                    $value
                )
            );
        }
    }

    public static function isBetween(int|float $min, int|float $max, int|float $value, bool $inclusive = true): void
    {
        $ok = $inclusive
            ? ($value >= $min && $value <= $max)
            : ($value > $min && $value < $max);

        if (!$ok) {
            throw new TestFailedException(
                new AssertionFailure(
                    "Expected value to be between {$min} and {$max}" . ($inclusive ? ' (inclusive)' : ' (exclusive)'),
                    actual: $value,
                    metadata: ['inclusive' => $inclusive]
                )
            );
        }
    }

    //Array
    public static function empty(array $array): void
    {
        if (!empty($array)) {
            throw new TestFailedException(
                new AssertionFailure(
                    'Expected array to be EMPTY',
                    null,
                    $array
                )
            );
        }
    }

    public static function notEmpty(array $array): void
    {
        if (empty($array)) {
            throw new TestFailedException(
                new AssertionFailure(
                    'Expected array to be NOT EMPTY',
                    actual: $array
                )
            );
        }
    }

    public static function contains(mixed $element, array $source, bool $shouldUseStrict = true): void
    {
        if (!in_array($element, $source, $shouldUseStrict)) {
            throw new TestFailedException(
                new AssertionFailure(
                    'Array does not contain the given element',
                    $element,
                    $source,
                    null,
                    ['strict' => $shouldUseStrict]
                )
            );
        }
    }

    public static function countEquals(int $expected, array|\Countable $source): void
    {
        $actual = count($source);

        if ($expected !== $actual) {
            throw new TestFailedException(
                new AssertionFailure(
                    'Array length mismatch',
                    $expected,
                    $actual
                )
            );
        }
    }

    public static function hasKey(mixed $key, array $array): void
    {
        if (!array_key_exists($key, $array)) {
            throw new TestFailedException(
                new AssertionFailure(
                    'Expected array to have key',
                    $key,
                    array_keys($array)
                )
            );
        }
    }

    public static function notHasKey(mixed $key, array $array): void
    {
        if (array_key_exists($key, $array)) {
            throw new TestFailedException(
                new AssertionFailure(
                    'Expected array NOT to have key',
                    "not having $key",
                    array_keys($array)
                )
            );
        }
    }

    public static function keysEqual(array $expectedKeys, array $array): void
    {
        $actualKeys = array_keys($array);
        sort($expectedKeys);
        sort($actualKeys);

        if ($expectedKeys !== $actualKeys) {
            throw new TestFailedException(
                new AssertionFailure(
                    'Expected array keys to be exactly equal',
                    $expectedKeys,
                    $actualKeys
                )
            );
        }
    }

    public static function containsOnly(array $allowedValues, array $array): void
    {
        foreach ($array as $item) {
            if (!in_array($item, $allowedValues, true)) {
                throw new TestFailedException(
                    new AssertionFailure(
                        'Array contains value(s) not allowed',
                        $allowedValues,
                        $array
                    )
                );
            }
        }
    }

    public static function instanceOf(string $expectedInstance, object $object): void
    {
        if (!($object instanceof $expectedInstance)) {
            throw new TestFailedException(
                new AssertionFailure(
                    "Object is not an instance of $expectedInstance",
                    $expectedInstance,
                    $object::class
                )
            );
        }
    }

    /** @param callable():void $method */
    public static function throws(callable $method, ?string $exceptionType = null): void
    {
        try {
            $method();
        } catch (\Throwable $e) {
            if ($exceptionType === null || $e instanceof $exceptionType) {
                return;
            }

            $actualExceptionType = $e::class;
            throw new TestFailedException(
                new AssertionFailure(
                    "Method threw Exception of wrong type",
                    $exceptionType,
                    $e::class,
                    null,
                    ['reason' => 'wrong_type']
                )
            );
        }

        throw new TestFailedException(
            new AssertionFailure(
                "Expected method to throw $exceptionType but no exception was thrown",
                $exceptionType,
                null,
                null,
                ['reason' => 'no_exception']
            )
        );
    }
}
