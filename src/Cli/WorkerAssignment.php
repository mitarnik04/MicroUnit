<?php

namespace MicroUnit\Cli;

/** @internal which share of the test plan a parallel worker process runs, and where it reports */
final readonly class WorkerAssignment
{
    /**
     * @param int<0, max> $index
     * @param int<1, max> $count
     */
    public function __construct(
        public int $index,
        public int $count,
        public string $outputFile,
    ) {}
}
