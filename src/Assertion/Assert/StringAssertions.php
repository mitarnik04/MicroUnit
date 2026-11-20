<?php

namespace MicroUnit\Assertion\Assert;

use MicroUnit\Helpers\Diff;

/** Strings and JSON. Part of Assert. */
trait StringAssertions
{
    /** matchesFormat() placeholders and the regular expressions they stand for. */
    private const array FORMAT_PLACEHOLDERS = [
        '%%' => '%', '%e' => DIRECTORY_SEPARATOR === '\\' ? '\\\\' : '\/', '%s' => '[^\r\n]+', '%S' => '[^\r\n]*',
        '%a' => '.+', '%A' => '.*', '%w' => '\s*', '%i' => '[+-]?\d+', '%d' => '\d+', '%x' => '[0-9a-fA-F]+',
        '%f' => '[+-]?\.?\d+\.?\d*(?:[Ee][+-]?\d+)?', '%c' => '.',
    ];

    public static function stringContains(string $needle, string $haystack, string $message = ''): void
    {
        self::check(str_contains($haystack, $needle), 'Failed asserting that the string contains the expected substring.', $needle, $haystack, customMessage: $message);
    }

    public static function stringNotContains(string $needle, string $haystack, string $message = ''): void
    {
        self::check(!str_contains($haystack, $needle), 'Failed asserting that the string does not contain the substring.', $needle, $haystack, customMessage: $message);
    }

    public static function stringContainsIgnoringCase(string $needle, string $haystack, string $message = ''): void
    {
        self::check(
            str_contains(self::lowercase($haystack), self::lowercase($needle)),
            'Failed asserting that the string contains the expected substring (case ignored).',
            $needle,
            $haystack,
            customMessage: $message,
        );
    }

    public static function stringStartsWith(string $prefix, string $actual, string $message = ''): void
    {
        self::check(str_starts_with($actual, $prefix), 'Failed asserting that the string starts with the expected prefix.', $prefix, $actual, customMessage: $message);
    }

    public static function stringEndsWith(string $suffix, string $actual, string $message = ''): void
    {
        self::check(str_ends_with($actual, $suffix), 'Failed asserting that the string ends with the expected suffix.', $suffix, $actual, customMessage: $message);
    }

    public static function matchesRegex(string $pattern, string $actual, string $message = ''): void
    {
        $result = preg_match($pattern, $actual);
        if ($result === false) {
            throw new \InvalidArgumentException("Invalid regular expression: $pattern");
        }

        self::check($result === 1, "Failed asserting that the string matches $pattern.", $pattern, $actual, customMessage: $message);
    }

    /**
     * Matches a format with placeholders, like PHP's own .phpt tests: %s (anything on one line),
     * %S (same, or nothing), %a / %A (anything, across lines / or nothing), %d (digits), %i (signed
     * integer), %f (float), %x (hex), %c (one character), %w (whitespace), %e (directory
     * separator), %% (a percent sign). Good for output with timestamps, paths or ids in it:
     *
     * Assert::matchesFormat("Saved order %d at %s\n", $output);
     */
    public static function matchesFormat(string $format, string $actual, string $message = ''): void
    {
        $pattern = '';
        foreach (preg_split('/(%[%esSaAwidxfc])/', str_replace("\r\n", "\n", $format), -1, PREG_SPLIT_DELIM_CAPTURE) ?: [] as $part) {
            $pattern .= self::FORMAT_PLACEHOLDERS[$part] ?? preg_quote($part, '/');
        }
        if (self::passes(preg_match('/^' . $pattern . '$/s', str_replace("\r\n", "\n", $actual)) === 1)) {
            return;
        }

        self::reject(
            'Failed asserting that the string matches the format.',
            $format,
            $actual,
            customMessage: $message,
            diff: Diff::fromLines(explode("\n", $format), explode("\n", $actual)),
        );
    }

    public static function equalsIgnoringCase(string $expected, string $actual, string $message = ''): void
    {
        self::check(self::lowercase($expected) === self::lowercase($actual), 'Failed asserting that two strings are equal (case ignored).', $expected, $actual, true, $message);
    }

    /**
     * Equal after trimming and collapsing every run of whitespace to one space: for HTML, SQL and
     * other output whose formatting does not matter.
     */
    public static function equalsIgnoringWhitespace(string $expected, string $actual, string $message = ''): void
    {
        $normalize = static fn(string $text): string => trim((string) preg_replace('/\s+/u', ' ', $text));

        self::check($normalize($expected) === $normalize($actual), 'Failed asserting that two strings are equal (whitespace ignored).', $expected, $actual, true, $message);
    }

    public static function isJson(string $actual, string $message = ''): void
    {
        if (!self::passes(json_validate($actual))) {
            self::reject('Failed asserting that the string is valid JSON: ' . json_last_error_msg() . '.', actual: $actual, customMessage: $message);
        }
    }

    /**
     * Same JSON document: key order and formatting are ignored, list order and types are not.
     * $expected may be a JSON string or the PHP value it should decode to.
     */
    public static function jsonEquals(mixed $expected, string $actualJson, string $message = ''): void
    {
        if (!json_validate($actualJson)) {
            self::check(false, 'Failed asserting that the string is valid JSON: ' . json_last_error_msg() . '.', actual: $actualJson, customMessage: $message);

            return;
        }
        $expectedValue = is_string($expected) && json_validate($expected) ? json_decode($expected, true) : json_decode((string) json_encode($expected), true);
        $expectedValue = self::sortedJsonKeys($expectedValue);
        $actualValue = self::sortedJsonKeys(json_decode($actualJson, true));
        if (self::passes($expectedValue === $actualValue)) {
            return;
        }

        $pretty = static fn(mixed $value): string => (string) json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
        $expectedPretty = $pretty($expectedValue);
        $actualPretty = $pretty($actualValue);
        self::reject(
            'Failed asserting that two JSON documents are equal.',
            $expectedPretty,
            $actualPretty,
            customMessage: $message,
            diff: Diff::fromLines(explode("\n", $expectedPretty), explode("\n", $actualPretty)),
        );
    }

    private static function lowercase(string $text): string
    {
        return function_exists('mb_strtolower') ? mb_strtolower($text) : strtolower($text);
    }

    private static function sortedJsonKeys(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }
        $value = array_map(self::sortedJsonKeys(...), $value);
        if (!array_is_list($value)) {
            ksort($value, SORT_STRING);
        }

        return $value;
    }
}
