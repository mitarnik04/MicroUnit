<?php

namespace MicroUnit\Assertion\Assert;

use MicroUnit\Helpers\Diff;

/** Files and directories. Part of Assert. */
trait FileAssertions
{
    public static function fileExists(string $path, string $message = ''): void
    {
        if (!self::passes(is_file($path))) {
            self::reject("Failed asserting that the file '$path' exists.", customMessage: $message);
        }
    }

    public static function fileNotExists(string $path, string $message = ''): void
    {
        if (!self::passes(!file_exists($path))) {
            self::reject("Failed asserting that '$path' does not exist.", customMessage: $message);
        }
    }

    public static function directoryExists(string $path, string $message = ''): void
    {
        if (!self::passes(is_dir($path))) {
            self::reject("Failed asserting that the directory '$path' exists.", customMessage: $message);
        }
    }

    public static function fileContains(string $needle, string $path, string $message = ''): void
    {
        $contents = self::readFile($path, $message);
        if ($contents !== null && !self::passes(str_contains($contents, $needle))) {
            self::reject("Failed asserting that the file '$path' contains the expected text.", $needle, $contents, customMessage: $message);
        }
    }

    /** The file's contents equal $expected exactly (line endings normalized). */
    public static function fileContentEquals(string $expected, string $path, string $message = ''): void
    {
        $contents = self::readFile($path, $message);
        if ($contents === null) {
            return;
        }
        $expected = str_replace("\r\n", "\n", $expected);
        $contents = str_replace("\r\n", "\n", $contents);
        if (self::passes($contents === $expected)) {
            return;
        }

        self::reject(
            "Failed asserting that the contents of '$path' equal the expected text.",
            $expected,
            $contents,
            customMessage: $message,
            diff: Diff::fromLines(explode("\n", $expected), explode("\n", $contents)),
        );
    }

    /** The file's contents; null (after failing the assertion, which counts it) when it cannot be read. */
    private static function readFile(string $path, string $message): ?string
    {
        $contents = is_file($path) ? @file_get_contents($path) : false;
        if ($contents === false) {
            self::check(false, "Failed asserting that the file '$path' exists and is readable.", customMessage: $message);

            return null;
        }

        return $contents;
    }
}
