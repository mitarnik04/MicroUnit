<?php
require_once __DIR__ . '/diff.php';

class Formatter
{
    private const SPACES_FIRST_INDENT_LEVEL = 9;
    private const SPACES_SUBSEQUENT_INDENT_LEVELS = 4;

    public static function formatDiff(mixed $expected, mixed $actual, int $indentLevel = 1, string $label = 'Diff'): string
    {
        $indent = self::getIndent($indentLevel);
        $header = $indent . '- Expected' . PHP_EOL
            . $indent . '+ Actual' . PHP_EOL;

        return $header . self::formatBlock(
            $label,
            Diff::generate(self::exportValue($expected), self::exportValue($actual)),
            $indentLevel
        );;
    }

    public static function formatValue(mixed $value, int $indentLevel = 1): string
    {
        $exported = self::exportValue($value);

        if (str_contains($exported, "\n")) {
            return self::formatLines(explode("\n", trim($exported)), $indentLevel + 1);
        }

        return "$exported ";
    }

    public static function formatLabelledValue(string $label, mixed $value, int $indentLevel = 1): string
    {
        $indent = self::getIndent($indentLevel);
        $formatted = self::formatValue($value, $indentLevel);
        $separator = str_contains($formatted, "\n") ? ":\n" : ": ";

        return $indent . $label . $separator . $formatted;
    }

    public static function formatBlock(string $label, string $content, int $indentLevel = 1): string
    {
        $block = self::formatLines(explode("\n", trim($content)), $indentLevel + 1);

        $indent = self::getIndent($indentLevel);
        return $indent . $label . ":\n" . $block;
    }

    public static function exportValue(mixed $value): string
    {
        return is_array($value) || is_object($value)
            ? print_r_with_types($value)
            : var_export($value, true) . ' ' . '(' . gettype($value) . ')';
    }

    private static function getIndent(int $indentLevel)
    {
        if ($indentLevel <= 0) {
            return '';
        }
        $firstIndent = str_repeat(' ', self::SPACES_FIRST_INDENT_LEVEL);
        if ($indentLevel === 1) {
            return $firstIndent;
        }

        return $firstIndent . str_repeat(' ', self::SPACES_SUBSEQUENT_INDENT_LEVELS);
    }

    private static function formatLines(array $lines, int $indentLevel = 1): string
    {
        $indent = self::getIndent($indentLevel);
        return implode(
            "\n",
            array_map(
                fn($line) => $indent . "$line ",
                $lines
            )
        );
    }
}

function print_r_with_types($var, $indent = 0)
{
    $spacing = str_repeat('    ', $indent);
    $output = '';

    if (is_array($var)) {
        $output .= "Array (\n";
        foreach ($var as $key => $value) {
            $output .= $spacing . "    [$key] => ";
            if (is_array($value) || is_object($value)) {
                $output .= print_r_with_types($value, $indent + 1);
            } else {
                $val = var_export($value, true);
                $type = gettype($value);
                $output .= "$val ($type)\n";
            }
        }
        $output .= $spacing . ")\n";
    } elseif (is_object($var)) {
        $class = get_class($var);
        $output .= "Object of class $class (\n";
        foreach (get_object_vars($var) as $prop => $value) {
            $output .= $spacing . "    [$prop] => ";
            if (is_array($value) || is_object($value)) {
                $output .= print_r_with_types($value, $indent + 1);
            } else {
                $val = var_export($value, true);
                $type = gettype($value);
                $output .= "$val ($type)\n";
            }
        }
        $output .= $spacing . ")\n";
    } else {
        $val = var_export($var, true);
        $type = gettype($var);
        $output .= "$val ($type)\n";
    }

    return $output;
}
