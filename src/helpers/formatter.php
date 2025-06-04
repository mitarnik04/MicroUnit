<?php
require_once __DIR__ . '/diff.php';

class Formatter
{
    private const SPACES_PER_INDENT_LEVEL = 4;

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
        $indent = self::getIndent($indentLevel);
        $exported = self::exportValue($value);

        if (str_contains($exported, "\n")) {
            return self::formatLines(explode("\n", trim($exported)), $indentLevel + 1);
        }

        return $indent . "$exported " . '(' . gettype($exported) . ')';
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
            ? print_r($value, true)
            : var_export($value, true);
    }

    private static function getIndent(int $indentLevel)
    {
        return str_repeat(str_repeat(' ', self::SPACES_PER_INDENT_LEVEL), $indentLevel);
    }

    private static function formatLines(array $lines, int $indentLevel = 1): string
    {
        $indent = self::getIndent($indentLevel);
        return implode(
            "\n",
            array_map(
                fn($line) => $indent . "$line " . '(' . gettype($line) . ')',
                $lines
            )
        );
    }
}
