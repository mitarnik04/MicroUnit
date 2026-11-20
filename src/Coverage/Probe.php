<?php

namespace MicroUnit\Coverage;

/**
 * Hit counters written by instrumented code: Probe::$hits[file][line]++. A plain static array is the
 * cheapest thing a probe can touch, which keeps instrumented code fast.
 *
 * @internal
 */
final class Probe
{
    /** @var array<string, array<int, int>> file => line => hits */
    public static array $hits = [];

    private function __construct() {}

    /** @param list<int> $executableLines */
    public static function register(string $file, array $executableLines): void
    {
        self::$hits[$file] ??= array_fill_keys($executableLines, 0);
    }
}
