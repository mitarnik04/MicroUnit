<?php

namespace MicroUnit\Helpers;

/** Renders any PHP value as readable, multi-line text for failure messages and diffs. */
final class ValueExporter
{
    private const string INDENT = '    ';

    private const string OMITTED = '…';

    private function __construct() {}

    /**
     * @param int $maxLength stop exporting at about this many bytes and mark the rest with "…": for
     *                       displays, where a big object graph would otherwise cost seconds and gigabytes
     *                       (an object referenced a thousand times is exported a thousand times)
     */
    public static function export(mixed $value, int $maxLength = PHP_INT_MAX): string
    {
        $budget = $maxLength;

        return self::exportValue($value, 0, [], $budget);
    }

    /** @param array<int, true> $objectsOnPath ids of the objects currently being exported (cycle guard) */
    private static function exportValue(mixed $value, int $depth, array $objectsOnPath, int &$budget): string
    {
        if ($budget <= 0) {
            return self::OMITTED;
        }

        return match (true) {
            $value instanceof ExportedValue => $value->representation,
            is_array($value) => self::exportArray($value, $depth, $objectsOnPath, $budget),
            $value instanceof \UnitEnum => $value::class . '::' . $value->name,
            $value instanceof \Closure => 'Closure',
            $value instanceof \DateTimeInterface => $value::class . '(' . $value->format('Y-m-d\TH:i:s.uP') . ')',
            is_object($value) => self::exportObject($value, $depth, $objectsOnPath, $budget),
            is_string($value) => self::exportString($value, $budget),
            default => self::exportScalar($value),
        };
    }

    private static function exportScalar(mixed $value): string
    {
        return match (true) {
            $value === null => 'null',
            is_bool($value) => ($value ? 'true' : 'false') . ' (bool)',
            is_int($value) => $value . ' (int)',
            is_float($value) => var_export($value, true) . ' (float)',
            default => get_debug_type($value),
        };
    }

    /** A string longer than the budget is cut (never inside a UTF-8 character) and its full length named. */
    private static function exportString(string $value, int $budget): string
    {
        $length = strlen($value);
        if ($length <= $budget) {
            return var_export($value, true) . ' (string)';
        }

        $cut = $budget;
        while ($cut > 0 && (ord($value[$cut]) & 0xC0) === 0x80) {
            $cut--;
        }

        return var_export(substr($value, 0, $cut), true) . self::OMITTED . " (string, $length bytes)";
    }

    /** @param array<int, true> $objectsOnPath */
    private static function exportArray(array $array, int $depth, array $objectsOnPath, int &$budget): string
    {
        if ($array === []) {
            return '[]';
        }

        $innerIndent = str_repeat(self::INDENT, $depth + 1);
        $lines = [];
        $remaining = count($array);
        foreach ($array as $key => $value) {
            $lines[] = self::entry($innerIndent . var_export($key, true) . ' => ', $value, $depth, $objectsOnPath, $budget);
            if (--$remaining > 0 && $budget <= 0) {
                $lines[] = $innerIndent . self::OMITTED;
                break;
            }
        }

        return "[\n" . implode("\n", $lines) . "\n" . str_repeat(self::INDENT, $depth) . ']';
    }

    /** @param array<int, true> $objectsOnPath */
    private static function exportObject(object $object, int $depth, array $objectsOnPath, int &$budget): string
    {
        $id = spl_object_id($object);
        if (isset($objectsOnPath[$id])) {
            return $object::class . ' { *RECURSION* }';
        }
        $objectsOnPath[$id] = true;

        // Mangled vars include private and protected properties, which matter for equality.
        $properties = get_mangled_object_vars($object);
        if ($properties === []) {
            return $object::class . ' {}';
        }

        $innerIndent = str_repeat(self::INDENT, $depth + 1);
        $lines = [];
        $remaining = count($properties);
        foreach ($properties as $mangledName => $value) {
            $lines[] = self::entry($innerIndent . self::unmangle((string) $mangledName) . ': ', $value, $depth, $objectsOnPath, $budget);
            if (--$remaining > 0 && $budget <= 0) {
                $lines[] = $innerIndent . self::OMITTED;
                break;
            }
        }

        return $object::class . " {\n" . implode("\n", $lines) . "\n" . str_repeat(self::INDENT, $depth) . '}';
    }

    /**
     * One line of an array or object, "$prefix<value>,", paid from the budget.
     *
     * @param array<int, true> $objectsOnPath
     */
    private static function entry(string $prefix, mixed $value, int $depth, array $objectsOnPath, int &$budget): string
    {
        $budget -= strlen($prefix) + 2;
        $exported = self::exportValue($value, $depth + 1, $objectsOnPath, $budget);
        if (!is_array($value) && !is_object($value)) {
            $budget -= strlen($exported);
        }

        return $prefix . $exported . ',';
    }

    /** "\0Class\0name" (private) and "\0*\0name" (protected) become "name". */
    private static function unmangle(string $name): string
    {
        if (!str_starts_with($name, "\0")) {
            return $name;
        }

        return substr($name, strrpos($name, "\0") + 1);
    }
}
