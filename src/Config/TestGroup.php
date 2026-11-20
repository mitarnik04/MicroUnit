<?php

namespace MicroUnit\Config;

/**
 * A named set of test files, e.g. "unit" and "integration". Run a group with --group=NAME;
 * without --group every group runs.
 */
final readonly class TestGroup
{
    /** @param list<string> $filePatterns file name globs; empty = the config's test file patterns */
    public function __construct(
        public string $name,
        public string $directory,
        public array $filePatterns = [],
    ) {}
}
