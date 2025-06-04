<?php
require_once __DIR__ . '/../core/test-failed-exception.php';

class Assert
{

    public static function equals(mixed $expected, mixed $actual)
    {
        if ($expected != $actual) {
            throw new TestFailedException(
                'The provided two values are not equal' . PHP_EOL
                    . '++++ Expected: ' . self::asReadableLine($expected)
                    . '---- Actual: ' . self::asReadableLine($actual)
            );
        }
    }

    public static function empty(array $array): void
    {
        if (!empty($array)) {
            throw new TestFailedException(
                'Expected array to be EMPTY.' . PHP_EOL .
                    '---- Actual: ' . self::asReadableLine($array)
            );
        }
    }

    public static function notEmpty(array $array): void
    {
        if (empty($array)) {
            throw new TestFailedException(
                'Expected array to be NOT EMPTY.' . PHP_EOL .
                    '---- Actual: []'
            );
        }
    }

    public static function contains(mixed $element, array $source, bool $shouldUseStrict = true): void
    {
        if (!in_array($element, $source, $shouldUseStrict)) {
            throw new TestFailedException(
                'Expected array to CONTAIN this element: ' . self::asReadableLine($element) .
                    '---- Array contents: ' . self::asReadableLine($source)
            );
        }
    }


    public static function countEquals(int $expected, array $source): void
    {
        $arrayCount = count($source);
        if ($expected !== $arrayCount) {
            throw new TestFailedException(
                "Array length mismatch: 
            ++++ Expected: $expected
            ---- Actual: $arrayCount"
            );
        }
    }

    public static function instanceOf($expectedInstance, $object)
    {
        if (!($object instanceof $expectedInstance)) {
            throw new TestFailedException(
                'The object is not an instance of $expectedInstance.' . PHP_EOL .
                    '---- Actual object type: ' . $object::class
            );
        }
    }

    /** @param callable() $method*/
    public static function throws(callable $method, ?string $exceptionType = null)
    {
        try {
            $method();
            throw new TestFailedException("Expected Method to throw exception of type: $exceptionType. 
            ---- Actually threw: No Excpetion");
        } catch (\Throwable $e) {
            if ($e instanceof TestFailedException) {
                throw $e;
            }
            if (isset($exceptionType) && !($e instanceof $exceptionType)) {
                $exceptionClassName = $e::class;
                throw new TestFailedException("Expected Method to throw exception of type: $exceptionType. 
                ---- Actually threw: $exceptionClassName");
            }
        }
    }

    private static function asReadableOutput(mixed $value, string $prefix = '', $suffix = '')
    {
        $stringValue = print_r($value);
        return $prefix . $stringValue . $suffix;
    }

    private static function asReadableLine(mixed $value, string $prefix = '', $suffix = '')
    {
        return self::asReadableOutput($value, $prefix, $suffix) . PHP_EOL;
    }
}
