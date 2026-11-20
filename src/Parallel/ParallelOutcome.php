<?php

namespace MicroUnit\Parallel;

use MicroUnit\Core\RunSummary;
use MicroUnit\Coverage\CoverageData;

final readonly class ParallelOutcome
{
    public function __construct(
        public RunSummary $summary,
        public ?CoverageData $coverage,
    ) {}
}
