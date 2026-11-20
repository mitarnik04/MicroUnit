<?php

namespace MicroUnit\Coverage;

/** What the run needs to know after coverage reports were written. */
final readonly class CoverageReport
{
    /** @param array{lineRate: float, coveredLines: int, executableLines: int, htmlReport: ?string} $summary */
    public function __construct(
        public bool $isBelowMinimum,
        public array $summary,
    ) {}
}
