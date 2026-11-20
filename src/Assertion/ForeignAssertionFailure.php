<?php

namespace MicroUnit\Assertion;

use MicroUnit\Helpers\Diff;
use MicroUnit\Helpers\ExportedValue;

/**
 * Translates assertion exceptions of other libraries into MicroUnit failures. Framework helpers
 * (Laravel's TestResponse, Symfony's BrowserKit assertions, ...) assert through PHPUnit; when PHPUnit
 * is installed those failures should read like MicroUnit failures, not like crashes.
 * No dependency: the classes are only checked by name.
 */
final class ForeignAssertionFailure
{
    private const int MAXIMUM_LINES = 12;

    private function __construct() {}

    public static function from(\Throwable $throwable): ?AssertionFailure
    {
        if (!is_a($throwable, 'PHPUnit\Framework\AssertionFailedError')) {
            return null;
        }

        $comparison = method_exists($throwable, 'getComparisonFailure') ? $throwable->getComparisonFailure() : null;
        if ($comparison === null) {
            return new AssertionFailure(self::readable($throwable->getMessage()));
        }

        $expected = (string) $comparison->getExpectedAsString();
        $actual = (string) $comparison->getActualAsString();

        return new AssertionFailure(
            self::firstLine($throwable->getMessage()),
            new ExportedValue($expected),
            new ExportedValue($actual),
            Diff::fromLines(explode("\n", $expected), explode("\n", $actual)),
        );
    }

    private static function firstLine(string $message): string
    {
        return strtok($message, "\n") ?: $message;
    }

    /** The whole message (it carries the details when there is no diff), with object dumps collapsed. */
    private static function readable(string $message): string
    {
        $message = preg_replace('/(\S+) Object #\d+ \(\n.*?\n\)/s', '$1', trim($message)) ?? $message;
        $lines = explode("\n", $message);

        return count($lines) > self::MAXIMUM_LINES ? implode("\n", array_slice($lines, 0, self::MAXIMUM_LINES)) . "\n…" : $message;
    }
}
