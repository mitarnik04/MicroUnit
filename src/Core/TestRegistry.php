<?php

namespace MicroUnit\Core;

/** Holds every suite's Tester in the order the suites were first requested. */
final class TestRegistry
{
    private static ?self $instance = null;

    /** @var array<string, Tester> */
    private array $testers = [];

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    public function tester(string $suite): Tester
    {
        return $this->testers[$suite] ??= new Tester($suite);
    }

    /** @return list<Tester> */
    public function testers(): array
    {
        return array_values($this->testers);
    }

    /**
     * Drops every registered test, and with them their data sets, at a point where exceptions are
     * still catchable. Left to PHP's shutdown, an exception from a destructor in test data (say, a
     * helper that asserts it was used, in a data set this process did not run: filtered out, or
     * another worker's share) would turn the exit code into 255.
     */
    public static function release(): void
    {
        try {
            self::$instance = null;
        } catch (\Throwable) {
            // Not a failure of this run: the tests that ran have reported their results already.
        }
    }
}
