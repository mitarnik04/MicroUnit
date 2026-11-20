<?php

namespace MicroUnit\Coverage\Report;

/**
 * How the reports name files and functions.
 *
 * @internal
 */
final class ReportNames
{
    private function __construct() {}

    /** $file relative to $baseDirectory with forward slashes, or unchanged when it lies outside. */
    public static function relativePath(string $file, string $baseDirectory): string
    {
        $base = rtrim($baseDirectory, '/\\') . DIRECTORY_SEPARATOR;

        return str_starts_with($file, $base) ? str_replace('\\', '/', substr($file, strlen($base))) : $file;
    }

    /** "App\Order::total" => "total"; plain function names stay as they are. */
    public static function shortFunctionName(string $name): string
    {
        $separator = strrpos($name, '::');

        return $separator === false ? $name : substr($name, $separator + 2);
    }
}
