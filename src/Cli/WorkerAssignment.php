<?php

namespace MicroUnit\Cli;

/** @internal which share of the test plan a parallel worker process runs, and where it reports */
final readonly class WorkerAssignment
{
    public function __construct(
        public int $index,
        public int $count,
        public string $outputFile,
    ) {}
}
