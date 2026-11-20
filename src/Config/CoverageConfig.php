<?php

namespace MicroUnit\Config;

/** Code coverage settings. Paths are relative to the config file (absolute paths work too). */
final readonly class CoverageConfig
{
    /**
     * @param list<string> $include directories or files whose code is measured
     * @param list<string> $exclude directories or files inside $include that are skipped
     * @param string|null $htmlDirectory where the HTML report is written
     * @param string|null $cloverFile Clover XML (Codecov, Coveralls, Jenkins, ...)
     * @param string|null $coberturaFile Cobertura XML (GitLab, Azure DevOps, ...)
     * @param bool $showTextSummary print a coverage table to the console after the run
     * @param float|null $minimumLineCoverage fail the run (exit code 1) below this percentage
     */
    public function __construct(
        public array $include = ['src'],
        public array $exclude = [],
        public ?string $htmlDirectory = null,
        public ?string $cloverFile = null,
        public ?string $coberturaFile = null,
        public bool $showTextSummary = true,
        public ?float $minimumLineCoverage = null,
    ) {}
}
