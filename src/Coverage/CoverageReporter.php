<?php

namespace MicroUnit\Coverage;

use MicroUnit\Bootstrap\LoadedConfig;
use MicroUnit\Config\CoverageConfig;
use MicroUnit\Coverage\Report\CloverCoverageReport;
use MicroUnit\Coverage\Report\CoberturaCoverageReport;
use MicroUnit\Coverage\Report\HtmlCoverageReport;
use MicroUnit\Coverage\Report\TextCoverageReport;
use MicroUnit\Output\Format\TextStyle;

/** Writes the configured coverage reports and checks the minimum. */
final readonly class CoverageReporter
{
    public function __construct(
        private LoadedConfig $loaded,
        private TextStyle $style,
        /** @var resource where the text goes (stderr in agent sessions, so stdout stays machine-readable) */
        private mixed $stream = STDOUT,
    ) {}

    public function report(CoverageData $data, CoverageConfig $config): CoverageReport
    {
        if ($config->showTextSummary) {
            fwrite($this->stream, new TextCoverageReport($this->style, $this->loaded->baseDirectory)->render($data));
        }

        $htmlReport = null;
        if ($config->htmlDirectory !== null) {
            $htmlReport = new HtmlCoverageReport($this->loaded->baseDirectory)->write($data, $this->loaded->resolve($config->htmlDirectory));
            fwrite($this->stream, $this->style->dim('  HTML report: ' . $htmlReport) . PHP_EOL);
        }
        if ($config->cloverFile !== null) {
            CloverCoverageReport::write($data, $this->loaded->resolve($config->cloverFile));
        }
        if ($config->coberturaFile !== null) {
            CoberturaCoverageReport::write($data, $this->loaded->resolve($config->coberturaFile), $this->loaded->baseDirectory);
        }

        $totals = $data->totals();
        $lineRate = CoverageData::percentage($totals['coveredLines'], $totals['executableLines']);
        $isBelowMinimum = $config->minimumLineCoverage !== null && $lineRate < $config->minimumLineCoverage;
        if ($isBelowMinimum) {
            fwrite($this->stream, $this->style->red(sprintf('  Line coverage %.1f%% is below the required minimum of %.1f%%.', $lineRate, $config->minimumLineCoverage)) . PHP_EOL);
        }

        return new CoverageReport($isBelowMinimum, [
            'lineRate' => $lineRate,
            'coveredLines' => $totals['coveredLines'],
            'executableLines' => $totals['executableLines'],
            'htmlReport' => $htmlReport,
        ]);
    }
}
