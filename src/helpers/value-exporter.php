<?php

class ValueExporter
{
    private const SPACING_UNIT = '   ';

    public static function export($var, int $indent = 0)
    {
        return match (true) {
            is_array($var) => self::exportArray($var, $indent),
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

    private static function exportArray(array $vals, int $indent = 0)
    {
        $spacing = self::getSpacing($indent);
        $innerSpacing = self::getSpacing($indent + 1);

        $output = "Array (" . PHP_EOL;
        foreach ($vals as $key => $value) {
            $output .= $innerSpacing . "[$key] => ";
            $output .= self::export($value, $indent + 1);
            $output .= PHP_EOL;
        }

        return $output . $spacing . ")";
    }

    private static function exportObject(object $obj, int $indent = 0)
    {
        $spacing = self::getSpacing($indent);
        $innerSpacing = self::getSpacing($indent + 1);

        $class = get_class($obj);
        $output = "Object of class $class (" . PHP_EOL;
        foreach (get_object_vars($obj) as $prop => $value) {
            $output .= $innerSpacing . "[$prop] => ";
            $output .= self::export($value, $indent + 1);
            $output .= PHP_EOL;
        }

        return $output . $spacing . ")";
    }

    private static function getSpacing(int $indent)
    {
        return str_repeat(self::SPACING_UNIT, $indent);
    }
}
