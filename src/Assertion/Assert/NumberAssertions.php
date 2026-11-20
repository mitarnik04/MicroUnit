<?php

namespace MicroUnit\Assertion\Assert;

/** Comparisons and tolerances for numbers and dates. Part of Assert. */
trait NumberAssertions
{
    public static function isGreaterThan(int|float $minimum, int|float $actual, string $message = ''): void
    {
        if (!self::passes($actual > $minimum)) {
            self::reject("Failed asserting that $actual is greater than $minimum.", "> $minimum", $actual, customMessage: $message);
        }
    }

    public static function isGreaterThanOrEqual(int|float $minimum, int|float $actual, string $message = ''): void
    {
        if (!self::passes($actual >= $minimum)) {
            self::reject("Failed asserting that $actual is greater than or equal to $minimum.", ">= $minimum", $actual, customMessage: $message);
        }
    }

    public static function isLessThan(int|float $maximum, int|float $actual, string $message = ''): void
    {
        if (!self::passes($actual < $maximum)) {
            self::reject("Failed asserting that $actual is less than $maximum.", "< $maximum", $actual, customMessage: $message);
        }
    }

    public static function isLessThanOrEqual(int|float $maximum, int|float $actual, string $message = ''): void
    {
        if (!self::passes($actual <= $maximum)) {
            self::reject("Failed asserting that $actual is less than or equal to $maximum.", "<= $maximum", $actual, customMessage: $message);
        }
    }

    public static function isBetween(int|float $minimum, int|float $maximum, int|float $actual, bool $inclusive = true, string $message = ''): void
    {
        $isBetween = $inclusive
            ? $actual >= $minimum && $actual <= $maximum
            : $actual > $minimum && $actual < $maximum;
        if (self::passes($isBetween)) {
            return;
        }

        $range = $inclusive ? "[$minimum, $maximum]" : "($minimum, $maximum)";
        self::reject("Failed asserting that $actual is within $range.", $range, $actual, customMessage: $message);
    }

    public static function equalsWithDelta(int|float $expected, int|float $actual, int|float $delta, string $message = ''): void
    {
        if (!self::passes(abs($expected - $actual) <= $delta)) {
            self::reject("Failed asserting that $actual equals $expected within a delta of $delta.", $expected, $actual, customMessage: $message);
        }
    }

    /** Relative tolerance: Assert::equalsWithinPercent(100, $measured, 5) accepts 95 to 105. */
    public static function equalsWithinPercent(int|float $expected, int|float $actual, int|float $percent, string $message = ''): void
    {
        if (!self::passes(abs($expected - $actual) <= abs($expected) * $percent / 100)) {
            self::reject("Failed asserting that $actual equals $expected within $percent%.", $expected, $actual, customMessage: $message);
        }
    }

    public static function isNan(mixed $actual, string $message = ''): void
    {
        self::check(is_float($actual) && is_nan($actual), 'Failed asserting that value is NAN.', NAN, $actual, customMessage: $message);
    }

    /** Not NAN and not ±INF. */
    public static function isFinite(int|float $actual, string $message = ''): void
    {
        self::check(is_finite((float) $actual), 'Failed asserting that the number is finite.', actual: $actual, customMessage: $message);
    }

    /**
     * Same moment in time, give or take $toleranceInSeconds; strings are parsed like new
     * DateTimeImmutable(). Assert::dateEquals('now', $user->createdAt, toleranceInSeconds: 5).
     */
    public static function dateEquals(\DateTimeInterface|string $expected, \DateTimeInterface|string $actual, int|float $toleranceInSeconds = 0, string $message = ''): void
    {
        $difference = abs(self::timestamp($expected) - self::timestamp($actual));
        if (self::passes($difference <= $toleranceInSeconds)) {
            return;
        }

        $format = static fn(\DateTimeInterface|string $date): string => is_string($date) ? $date : $date->format('Y-m-d H:i:s.uP');
        self::reject(
            sprintf('Failed asserting that the dates are equal%s (they differ by %s s).', $toleranceInSeconds > 0 ? " within $toleranceInSeconds s" : '', round($difference, 6)),
            $format($expected),
            $format($actual),
            customMessage: $message,
        );
    }

    private static function timestamp(\DateTimeInterface|string $date): float
    {
        return (float) (is_string($date) ? new \DateTimeImmutable($date) : $date)->format('U.u');
    }
}
