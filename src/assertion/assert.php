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
                    Formatter::formatLabelledValue('Actual', '[]')
            );
        }
    }

    public static function contains(mixed $element, array $source, bool $shouldUseStrict = true): void
    {
        if (!in_array($element, $source, $shouldUseStrict)) {
            throw new TestFailedException(
                'Expected array to CONTAIN this element: ' . Formatter::formatValue($element) . PHP_EOL .
                    Formatter::formatLabelledValue('Array contents', $source)
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

    public static function instanceOf($expectedInstance, $object)
    {
        if (!($object instanceof $expectedInstance)) {
            throw new TestFailedException(
                "The object is not an instance of $expectedInstance." . PHP_EOL .
                    Formatter::formatLabelledValue('Actual object type', $object::class)
            );
        }
    }

    /** @param callable() $method*/
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
