<?php

/** Exports a value of any kind into a human readable format */
class ValueExporter
{
    private const SPACING_UNIT = '   ';

    public static function export($var, int $indent = 0): string
    {
        return match (true) {
            is_array($var) => self::exportArray($var, $indent),
            $var instanceof Diff => self::exportDiff($var, $indent),
            is_object($var) => self::exportObject($var, $indent),
            default => self::exportScalar($var),
        };
    }


    private static function exportScalar(mixed $val): string
    {
        $exported = var_export($val, true);
        $type = gettype($val);
        return "$exported ($type)";
    }

    private static function exportArray(array $vals, int $indent = 0): string
    {
        $innerSpacing = self::getSpacing($indent + 1);

        $output = "Array (" . PHP_EOL;
        foreach ($vals as $key => $value) {
            $output .= $innerSpacing . "[$key] => ";
            $output .= self::export($value, $indent + 1);
            $output .= PHP_EOL;
        }
        $output .= ')';

        return $output;
    }

    private static function exportObject(object $obj, int $indent = 0): string
    {
        $innerSpacing = self::getSpacing($indent + 1);
        $class = get_class($obj);

        $output = "Object of class $class (" . PHP_EOL;

        foreach (get_object_vars($obj) as $prop => $value) {
            $output .= $innerSpacing . "[$prop] => ";
            $output .= self::export($value, $indent + 1);
            $output .= PHP_EOL;
        }
        $output .= ')';

        return $output;
    }

    private static function exportDiff(Diff $diff, int $indent = 0)
    {
        $innerSpacing = self::getSpacing($indent + 1);

        $output = '--- Expected' . PHP_EOL;
        $output .= '+++ Actual' . PHP_EOL;
        $output .= 'Diff: ' . PHP_EOL;

        foreach ($diff->diffLines as $diffLine) {
            $prefix = match ($diffLine->type) {
                DiffLineType::Same => '',
                DiffLineType::ExpectedDifferent => '-',
                DiffLineType::AcutalDifferent => '+'
            };
            $line = $diffLine->line;
            $output .= $innerSpacing . "$prefix $line";
            $output .= PHP_EOL;
        }

        return trim($output);
    }

    private static function getSpacing(int $indent): string
    {
        return str_repeat(self::SPACING_UNIT, $indent);
    }
}
