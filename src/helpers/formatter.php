<?php
require_once __DIR__ . '/diff.php';
require_once __DIR__ . '/value-exporter.php';

class Formatter
{
    private const BASE_INDENT = 9;
    private const INDENT_PER_LEVEL = 4;

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

        if (str_contains($exported, PHP_EOL)) {
            return self::formatLines(explode(PHP_EOL, trim($exported)), $indentLevel + 1);
        }

        return $exported;
    }

    public static function formatLabelledValue(string $label, mixed $value, int $indentLevel = 1, bool $asLiteral = false): string
    {
        $indent = self::getIndent($indentLevel);
        $formatted = self::formatValue($value, $indentLevel, $asLiteral);
        $separator = str_contains($formatted, PHP_EOL) ? ':' . PHP_EOL : ': ';

        return $indent . $label . $separator . $formatted;
    }

    public static function formatBlock(string $label, string $content, int $indentLevel = 1): string
    {
        $block = self::formatLines(explode(PHP_EOL, trim($content)), $indentLevel + 1);

        $indent = self::getIndent($indentLevel);
        return $indent . $label . ':' . PHP_EOL . $block;
    }

    private static function getIndent(int $indentLevel)
    {
        $firstIndent = str_repeat(' ', self::BASE_INDENT);
        return match (true) {
            $indentLevel <= 0 => '',
            $indentLevel === 1 => $firstIndent,
            default => $firstIndent . str_repeat(' ', self::INDENT_PER_LEVEL * ($indentLevel - 1))
        };
    }

    private static function formatLines(array $lines, int $indentLevel = 1): string
    {
        $indent = self::getIndent($indentLevel);
        return implode(
            PHP_EOL,
            array_map(
                fn($line) => $indent . $line,
                $lines
            )
        );
    }
}
