<?php
require_once __DIR__ . '/diff.php';
require_once __DIR__ . '/value-exporter.php';

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
            Diff::generate(ValueExporter::export($expected), ValueExporter::export($actual)),
            $indentLevel
        );
    }

    public static function formatValue(mixed $value, int $indentLevel = 1, bool $asLiteral = false): string
    {
        $exported = $asLiteral ? $value : ValueExporter::export($value);

        if (str_contains($exported, "\n")) {
            return self::formatLines(explode("\n", trim($exported)), $indentLevel + 1);
        }

        return $exported;
    }

    public static function formatLabelledValue(string $label, mixed $value, int $indentLevel = 1, bool $asLiteral = false): string
    {
        $indent = self::getIndent($indentLevel);
        $formatted = self::formatValue($value, $indentLevel, $asLiteral);
        $separator = str_contains($formatted, "\n") ? ":\n" : ": ";

        return $indent . $label . $separator . $formatted;
    }

    public static function formatBlock(string $label, string $content, int $indentLevel = 1): string
    {
        $block = self::formatLines(explode("\n", trim($content)), $indentLevel + 1);

        $indent = self::getIndent($indentLevel);
        return $indent . $label . ":\n" . $block;
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

        return $firstIndent . str_repeat(' ', self::SPACES_SUBSEQUENT_INDENT_LEVELS * ($indentLevel - 1));
    }

    private static function formatLines(array $lines, int $indentLevel = 1): string
    {
        $indent = self::getIndent($indentLevel);
        return implode(
            "\n",
            array_map(
                fn($line) => $indent . $line,
                $lines
            )
        );
    }
}
