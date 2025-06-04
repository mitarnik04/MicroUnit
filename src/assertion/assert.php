<?php
require_once __DIR__ . '/../core/test-failed-exception.php';

class Assert
{

    public static function equals(mixed $expected, mixed $actual)
    {
        if ($expected != $actual) {
            throw new TestFailedException(
                'The provided two values are not equal' . PHP_EOL
                    . self::formatLabelledValue('Expected', $expected) . PHP_EOL
                    . self::formatLabelledValue('Actual', $actual)
            );
        }
    }

    public static function empty(array $array): void
    {
        if (!empty($array)) {
            throw new TestFailedException(
                'Expected array to be EMPTY.' . PHP_EOL .
                    self::formatLabelledValue('Actual', $array)
            );
        }
    }

    public static function notEmpty(array $array): void
    {
        if (empty($array)) {
            throw new TestFailedException(
                'Expected array to be NOT EMPTY.' . PHP_EOL .
                    self::formatLabelledValue('Actual', '[]')
            );
        }
    }

    public static function contains(mixed $element, array $source, bool $shouldUseStrict = true): void
    {
        if (!in_array($element, $source, $shouldUseStrict)) {
            throw new TestFailedException(
                'Expected array to CONTAIN this element: ' . self::formatValue($element) . PHP_EOL .
                    self::formatLabelledValue('Array contents', $source)
            );
        }
    }


    public static function countEquals(int $expected, array $source): void
    {
        $arrayCount = count($source);
        if ($expected !== $arrayCount) {
            throw new TestFailedException(
                'Array length mismatch:' . PHP_EOL .
                    self::formatLabelledValue('Expected', $expected) . PHP_EOL .
                    self::formatLabelledValue('Actual', $arrayCount)
            );
        }
    }

    public static function instanceOf($expectedInstance, $object)
    {
        if (!($object instanceof $expectedInstance)) {
            throw new TestFailedException(
                "The object is not an instance of $expectedInstance." . PHP_EOL .
                    self::formatLabelledValue('Actual object type', $object::class)
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
                    self::formatLabelledValue('Actually threw', 'No Excpetion')
            );
        } catch (\Throwable $e) {
            if ($e instanceof TestFailedException) {
                throw $e;
            }
            if (isset($exceptionType) && !($e instanceof $exceptionType)) {
                throw new TestFailedException(
                    "Expected Method to throw exception of type: $exceptionType." . PHP_EOL .
                        self::formatLabelledValue('Actually threw', $e::class)
                );
            }
        }
    }

    private static function formatValue(mixed $value, int $indentLevel = 1): string
    {
        $indent = str_repeat("    ", $indentLevel);
        $subIndent = $indent . "    ";

        $exported = self::exportValue($value);
        if (str_contains($exported, "\n")) {
            // Multiline: indent each line manually with foreach for better performance !
            $lines = explode("\n", trim($exported));
            foreach ($lines as &$line) {
                $line = $subIndent . $line;
            }

            return implode("\n", $lines);
        }

        return $exported;
    }

    private static function formatLabelledValue(string $label, mixed $value, int $indentLevel = 1): string
    {
        $indent = str_repeat("    ", $indentLevel);
        $exported = self::formatValue($value, $indentLevel);
        $seperator = ': ';
        if (str_contains($exported, "\n")) {
            $seperator = ":\n";
        }

        return $indent . $label . $seperator . $exported;
    }

    private static function exportValue(mixed $value): string
    {
        if (is_array($value) || is_object($value)) {
            return print_r($value, true);
        }

        return var_export($value, true);
    }
}
