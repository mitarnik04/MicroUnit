<?php

namespace MicroUnit\Assertion\Assert;

use MicroUnit\Helpers\ValueExporter;

/** Equality, identity, null/bool checks, types and structure. Part of Assert. */
trait ValueAssertions
{
    /** Loose comparison (==). */
    public static function equals(mixed $expected, mixed $actual, string $message = ''): void
    {
        self::check(self::looselyEqual($expected, $actual), 'Failed asserting that two values are equal.', $expected, $actual, true, $message);
    }

    public static function notEquals(mixed $unexpected, mixed $actual, string $message = ''): void
    {
        self::check(!self::looselyEqual($unexpected, $actual), 'Failed asserting that two values are not equal.', $unexpected, $actual, customMessage: $message);
    }

    /** Strict comparison (===): same type and value, same instance for objects. */
    public static function exact(mixed $expected, mixed $actual, string $message = ''): void
    {
        self::check($expected === $actual, 'Failed asserting that two values are identical (type and value).', $expected, $actual, true, $message);
    }

    public static function notExact(mixed $unexpected, mixed $actual, string $message = ''): void
    {
        self::check($unexpected !== $actual, 'Failed asserting that two values are not identical.', $unexpected, $actual, customMessage: $message);
    }

    public static function isTrue(mixed $actual, string $message = ''): void
    {
        self::check($actual === true, 'Failed asserting that value is true.', true, $actual, customMessage: $message);
    }

    public static function isFalse(mixed $actual, string $message = ''): void
    {
        self::check($actual === false, 'Failed asserting that value is false.', false, $actual, customMessage: $message);
    }

    public static function isNull(mixed $actual, string $message = ''): void
    {
        self::check($actual === null, 'Failed asserting that value is null.', null, $actual, customMessage: $message);
    }

    public static function notNull(mixed $actual, string $message = ''): void
    {
        self::check($actual !== null, 'Failed asserting that value is not null.', actual: $actual, customMessage: $message);
    }

    /** @param class-string $expectedClass */
    public static function instanceOf(string $expectedClass, mixed $actual, string $message = ''): void
    {
        if (!self::passes($actual instanceof $expectedClass)) {
            self::reject("Failed asserting that value is an instance of $expectedClass.", $expectedClass, get_debug_type($actual), customMessage: $message);
        }
    }

    /**
     * Asserts the type of a value, written the way PHP writes types: 'int', '?string', 'int|float',
     * 'list', 'numeric', 'callable', or a class/interface/enum name.
     */
    public static function isType(string $expectedType, mixed $actual, string $message = ''): void
    {
        if (!self::passes(self::isOfTypeExpression($expectedType, $actual))) {
            self::reject("Failed asserting that value is of type $expectedType.", $expectedType, get_debug_type($actual), customMessage: $message);
        }
    }

    /**
     * Same contents, order ignored: lists are compared as multisets, maps by key, recursively.
     * Values are compared like equals(). For "same items in any order" checks of results.
     */
    public static function equivalent(mixed $expected, mixed $actual, string $message = ''): void
    {
        $expected = self::canonical($expected);
        $actual = self::canonical($actual);

        // Sorted alike, equal elements line up, which settles almost every case. Equal values of
        // different types (5 and '5') can sort apart; only then are the elements paired one by one.
        $isEquivalent = self::looselyEqual($expected, $actual) || self::equalInAnyOrder($expected, $actual);
        self::check($isEquivalent, 'Failed asserting that two values are equivalent (order ignored).', $expected, $actual, true, $message);
    }

    /**
     * Asserts that $actual contains at least the keys of $subset with equal values, recursively;
     * everything else in $actual is ignored. Arrays, stdClass objects (e.g. decoded JSON) and
     * ArrayAccess objects can be inspected.
     *
     * Assert::containsSubset(['user' => ['name' => 'Ada']], $response);
     *
     * @param array<mixed>|object $subset
     */
    public static function containsSubset(array|object $subset, mixed $actual, string $message = ''): void
    {
        $missing = [];
        $projected = self::project($actual, $subset, '', $missing);
        if (self::passes($missing === [] && self::looselyEqual($subset, $projected))) {
            return;
        }

        $description = $missing === []
            ? 'Failed asserting that the value contains the expected subset.'
            : 'Failed asserting that the value contains the expected subset; missing: ' . implode(', ', $missing) . '.';
        self::reject($description, $subset, $projected, true, $message);
    }

    public static function hasProperty(string $property, object $actual, string $message = ''): void
    {
        if (!self::passes(property_exists($actual, $property))) {
            self::reject(
                'Failed asserting that ' . $actual::class . " has the property '$property'.",
                $property,
                array_keys(get_object_vars($actual)),
                customMessage: $message,
            );
        }
    }

    /** A whole type expression: '?string', 'int|float', 'list', a class name, ... */
    private static function isOfTypeExpression(string $expectedType, mixed $value): bool
    {
        if (str_starts_with($expectedType, '?')) {
            if ($value === null) {
                return true;
            }
            $expectedType = substr($expectedType, 1);
        }
        foreach (explode('|', $expectedType) as $type) {
            if (self::isOfType(trim($type), $value)) {
                return true;
            }
        }

        return false;
    }

    private static function isOfType(string $type, mixed $value): bool
    {
        return match (strtolower($type)) {
            'mixed' => true,
            'null' => $value === null,
            'bool' => is_bool($value),
            'true' => $value === true,
            'false' => $value === false,
            'int' => is_int($value),
            'float' => is_float($value),
            'string' => is_string($value),
            'array' => is_array($value),
            'list' => is_array($value) && array_is_list($value),
            'object' => is_object($value),
            'callable' => is_callable($value),
            'iterable' => is_iterable($value),
            'numeric' => is_numeric($value),
            'scalar' => is_scalar($value),
            'resource' => is_resource($value),
            default => class_exists($type) || interface_exists($type) || enum_exists($type)
                ? $value instanceof $type
                : throw new \InvalidArgumentException("Unknown type '$type' for Assert::isType()."),
        };
    }

    /** Lists sorted by their exported form, maps by key, recursively. */
    private static function canonical(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }
        $value = array_map(self::canonical(...), $value);
        if (!array_is_list($value)) {
            ksort($value);

            return $value;
        }

        // Export every element once, not once per comparison of the sort.
        $sortKeys = array_map(ValueExporter::export(...), $value);
        asort($sortKeys);
        $sorted = [];
        foreach (array_keys($sortKeys) as $index) {
            $sorted[] = $value[$index];
        }

        return $sorted;
    }

    /** equivalent() without relying on the sort order: list elements are paired one by one. */
    private static function equalInAnyOrder(mixed $expected, mixed $actual): bool
    {
        if (!is_array($expected) || !is_array($actual)) {
            return self::looselyEqual($expected, $actual);
        }
        if (count($expected) !== count($actual)) {
            return false;
        }
        if (!array_is_list($expected) || !array_is_list($actual)) {
            foreach ($expected as $key => $value) {
                if (!array_key_exists($key, $actual) || !self::equalInAnyOrder($value, $actual[$key])) {
                    return false;
                }
            }

            return true;
        }

        foreach ($expected as $value) {
            $match = array_find_key($actual, static fn(mixed $candidate): bool => self::equalInAnyOrder($value, $candidate));
            if ($match === null) {
                return false;
            }
            unset($actual[$match]);
        }

        return true;
    }

    /**
     * $actual reduced to the keys $shape has, in $shape's form; absent keys are listed in $missing.
     *
     * @param list<string> $missing
     */
    private static function project(mixed $actual, mixed $shape, string $path, array &$missing): mixed
    {
        $isStructure = is_array($shape) || ($shape instanceof \stdClass);
        if (!$isStructure) {
            return $actual;
        }

        $projected = [];
        foreach ((array) $shape as $key => $expectedValue) {
            $keyPath = $path === '' ? (string) $key : "$path.$key";
            if (is_array($actual) && array_key_exists($key, $actual)) {
                $value = $actual[$key];
            } elseif ($actual instanceof \ArrayAccess && $actual->offsetExists($key)) {
                $value = $actual[$key];
            } elseif (is_object($actual) && array_key_exists((string) $key, get_object_vars($actual))) {
                $value = $actual->{$key};
            } else {
                $missing[] = $keyPath;
                continue;
            }
            $projected[$key] = self::project($value, $expectedValue, $keyPath, $missing);
        }

        return is_array($shape) ? $projected : (object) $projected;
    }
}
