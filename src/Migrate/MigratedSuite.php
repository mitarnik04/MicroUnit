<?php

namespace MicroUnit\Migrate;

/** The MicroUnit version of one PHPUnit test class. */
final readonly class MigratedSuite
{
    /**
     * @param list<string> $migratedTests names of the tests that were translated completely
     * @param array<string, list<string>> $skippedTests test name => why it needs manual migration (emitted as a skipped test)
     * @param list<string> $issues problems that affect the whole file
     * @param array<string, string> $methods original PHPUnit method => MicroUnit test name
     */
    public function __construct(
        public string $suite,
        public string $code,
        public array $migratedTests,
        public array $skippedTests,
        public array $issues,
        public array $methods = [],
    ) {}
}
