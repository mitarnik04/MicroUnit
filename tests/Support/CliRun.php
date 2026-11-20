<?php

namespace MicroUnit\Tests\Support;

/** What one `microunit` process did. */
final readonly class CliRun
{
    public function __construct(
        public int $exitCode,
        public string $output,
        public string $errors,
    ) {}
}
