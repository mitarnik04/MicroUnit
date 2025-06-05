<?php
require_once __DIR__ . '/../core/test-failed-exception.php';
require_once __DIR__ . '/../helpers/formatter.php';

class Assert
{
    public static function equals(mixed $expected, mixed $actual)
    {
        if ($expected != $actual) {
            throw new TestFailedException(
                'The provided two values are not equal' . PHP_EOL
                    . Formatter::formatDiff($expected, $actual)
            );
        }
    }

    public static function notEquals(mixed $expected, $actual)
    {
        if ($expected == $actual) {
            throw new TestFailedException(
                'Expected two values to be not equal. But actually they are' . PHP_EOL
                    . Formatter::formatLabelledValue("Value", $expected)
            );
        }
    }

    public static function exact(mixed $expected, mixed $actual)
    {
        if ($expected !== $actual) {
            throw new TestFailedException(
                'The provided two values are not exactly equal (type, value)' . PHP_EOL
                    . Formatter::formatDiff($expected, $actual)
            );
        }
    }

    public static function notExact(mixed $expected, $actual)
    {
        if ($expected === $actual) {
            throw new TestFailedException(
                'Expected two values to not be exactly equal (type, value). But actually they are' . PHP_EOL
                    . Formatter::formatLabelledValue("Value", $expected)
            );
        }
    }

    public static function isTrue(mixed $value): void
    {
        if ($value !== true) {
            throw new TestFailedException(
                'Expected TRUE' . PHP_EOL .
                    Formatter::formatLabelledValue('Got', $value)
            );
        }
    }

    public static function isFalse(mixed $value): void
    {
        if ($value !== false) {
            throw new TestFailedException(
                'Expected FALSE' . PHP_EOL .
                    Formatter::formatLabelledValue('Got', $value)
            );
        }
    }

    public static function isNull(mixed $value): void
    {
        if ($value !== null) {
            throw new TestFailedException(
                'Expected value to be NULL.' . PHP_EOL .
                    Formatter::formatLabelledValue('Got', $value)
            );
        }
    }

    public static function notNull(mixed $value): void
    {
        if ($value === null) {
            throw new TestFailedException('Expected NOT NULL but got NULL.');
        }
    }

    public static function isGreaterThan(int | float $expected, int | float $actual): void
    {
        if (!($actual > $expected)) {
            throw new TestFailedException(
                'Expected value to be greater than ' . Formatter::formatValue($expected) . PHP_EOL .
                    Formatter::formatLabelledValue('Actual', $actual)
            );
        }
    }

    public static function isLessThan(int | float  $expected, int | float  $actual): void
    {
        if (!($actual < $expected)) {
            throw new TestFailedException(
                'Expected value to be less than ' . Formatter::formatValue($expected) . PHP_EOL .
                    Formatter::formatLabelledValue('Actual', $actual)
            );
        }
    }

    public static function isBetween(int | float  $min, int | float  $max, int | float  $actual, bool $inclusive = true): void
    {
        $ok = $inclusive
            ? ($actual >= $min && $actual <= $max)
            : ($actual > $min && $actual < $max);

        if (!$ok) {
            $inclusivity = $inclusive ? 'inclusive' : 'exclusive';
            throw new TestFailedException(
                "Expected value to be between {$min} and {$max} ({$inclusivity})" . PHP_EOL .
                    Formatter::formatLabelledValue('Actual', $actual)
            );
        }
    }

    public static function empty(array $array): void
    {
        if (!empty($array)) {
            throw new TestFailedException(
                'Expected array to be EMPTY.' . PHP_EOL .
                    Formatter::formatLabelledValue('Actual', $array)
            );
        }
    }

    public static function notEmpty(array $array): void
    {
        if (empty($array)) {
            throw new TestFailedException(
                'Expected array to be NOT EMPTY.' . PHP_EOL .
                    Formatter::formatLabelledValue('Actual', '[]', asLiteral: true)
            );
        }
    }

    public static function contains(mixed $element, array $source, bool $shouldUseStrict = true): void
    {
        if (!in_array($element, $source, $shouldUseStrict)) {
            throw new TestFailedException(
                'Array does not contain the given element. ' . PHP_EOL
                    . Formatter::formatLabelledValue('Expected to contain', $element) . PHP_EOL
                    . Formatter::formatLabelledValue('Actual', $source)
            );
        }
    }

    public static function countEquals(int $expected, array | Countable $source): void
    {
        $arrayCount = count($source);
        if ($expected !== $arrayCount) {
            throw new TestFailedException(
                'Array length mismatch:' . PHP_EOL .
                    Formatter::formatLabelledValue('Expected', $expected) . PHP_EOL .
                    Formatter::formatLabelledValue('Actual', $arrayCount)
            );
        }
    }

    public static function hasKey(mixed $key, array $array): void
    {
        if (!array_key_exists($key, $array)) {
            throw new TestFailedException(
                'Expected array to have key: ' . Formatter::formatValue($key) . PHP_EOL .
                    Formatter::formatLabelledValue('Array keys', array_keys($array))
            );
        }
    }

    public static function notHasKey(mixed $key, array $array): void
    {
        if (array_key_exists($key, $array)) {
            throw new TestFailedException(
                'Expected array NOT to have key: ' . Formatter::formatValue($key) . PHP_EOL .
                    Formatter::formatLabelledValue('Array keys', array_keys($array))
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
                'Expected array keys to be exactly equal' . PHP_EOL .
                    Formatter::formatLabelledValue('Expected keys', $expectedKeys) . PHP_EOL .
                    Formatter::formatLabelledValue('Actual keys', $actualKeys)
            );
        }
    }

    public static function containsOnly(array $allowedValues, array $array): void
    {
        foreach ($array as $item) {
            if (!in_array($item, $allowedValues, true)) {
                throw new TestFailedException(
                    'Array contains value(s) not specified as allowed' . PHP_EOL .
                        Formatter::formatLabelledValue('Allowed', $allowedValues) . PHP_EOL .
                        Formatter::formatLabelledValue('Actual', $array)
                );
            }
        }
    }

    public static function instanceOf($expectedInstance, $object)
    {
        if (!($object instanceof $expectedInstance)) {
            throw new TestFailedException(
                "The object is not an instance of $expectedInstance." . PHP_EOL .
                    Formatter::formatLabelledValue('Actual object type', $object::class, asLiteral: true)
            );
        }
    }

    /** @param callable() $method */
    public static function throws(callable $method, ?string $exceptionType = null)
    {
        try {
            $method();
            throw new TestFailedException(
                "Expected Method to throw exception of type: $exceptionType." . PHP_EOL .
                    Formatter::formatLabelledValue('Actually threw', 'No Excpetion')
            );
        } catch (\Throwable $e) {
            if ($e instanceof TestFailedException) {
                throw $e;
            }
            if (isset($exceptionType) && !($e instanceof $exceptionType)) {
                throw new TestFailedException(
                    "Expected Method to throw exception of type: $exceptionType." . PHP_EOL .
                        Formatter::formatLabelledValue('Actually threw', $e::class)
                );
            }
        }
    }
}
