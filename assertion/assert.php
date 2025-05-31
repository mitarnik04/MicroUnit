<?php
require_once __DIR__ . '/../test-failed-exception.php';

class Assert
{
    public static function empty(array $array): void
    {
        if (!empty($array)) {
            throw new TestFailedException(
                "Expected array to be EMPTY.
            Actual: " . asReadableOutput($array, 3)
            );
        }
    }

    public static function notEmpty(array $array): void
    {
        if (empty($array)) {
            throw new TestFailedException(
                "Expected array to be NOT EMPTY.
            Actual: []"
            );
        }
    }

    public static function contains(mixed $element, array $source, bool $shouldUseStrict = true): void
    {
        if (!in_array($element, $source, $shouldUseStrict)) {
            throw new TestFailedException(
                "Expected array to CONTAIN this element: " . asReadableOutput($element, 3) . "
            Array contents: " . asReadableOutput($source, 3)
            );
        }
    }


    public static function countEquals(int $expected, array $source): void
    {
        $arrayCount = count($source);
        if ($expected !== $arrayCount) {
            throw new TestFailedException(
                "Array length mismatch: 
            Expected: $expected
            Actual: $arrayCount"
            );
        }
    }

    public static function instanceOf($expectedInstance, $object)
    {
        if (!($object instanceof $expectedInstance)) {
            $objClassName = $object::class;
            throw new TestFailedException(
                "The object is not an instance of $expectedInstance. 
                    Actual object type: $objClassName"
            );
        }
    }

    /** @param callable() $method*/
    public static function throws(callable $method, ?string $exceptionType = null)
    {
        try {
            $method();

            throw new TestFailedException("Expected Method to throw exception of type: $exceptionType. 
            Actually threw: No Excpetion");
        } catch (\Throwable $e) {
            if ($e instanceof TestFailedException) {
                throw $e;
            }
            if (isset($exceptionType) && !($e instanceof $exceptionType)) {
                $exceptionClassName = $e::class;
                throw new TestFailedException("Expected Method to throw exception of type: $exceptionType. 
                Actually threw: $exceptionClassName");
            }
        }
    }
}

function asReadableOutput(mixed $source, int $indent = 0)
{
    $json = var_export($source, true);
    $tabs = str_repeat("\t", $indent);
    return str_replace("\n", "\n $tabs", $json);
}
