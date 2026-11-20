<?php

namespace MicroUnit\Coverage\Report;

use MicroUnit\Coverage\CoverageData;
use MicroUnit\Output\Format\TextStyle;

/** Console summary: totals first, then every file that is not fully covered. */
final readonly class TextCoverageReport
{
    public function __construct(
        private TextStyle $style,
        private string $baseDirectory,
    ) {}

    public function render(CoverageData $data): string
    {
        $totals = $data->totals();
        $linePercentage = CoverageData::percentage($totals['coveredLines'], $totals['executableLines']);
        $functionPercentage = CoverageData::percentage($totals['coveredFunctions'], $totals['functions']);

        $output = PHP_EOL . $this->style->bold('Code coverage') . PHP_EOL
            . '  Lines:     ' . $this->colored($linePercentage) . $this->style->dim(" ({$totals['coveredLines']}/{$totals['executableLines']})") . PHP_EOL
            . '  Functions: ' . $this->colored($functionPercentage) . $this->style->dim(" ({$totals['coveredFunctions']}/{$totals['functions']})") . PHP_EOL;

        $incomplete = [];
        $fullyCovered = 0;
        foreach (array_keys($data->files) as $file) {
            $fileTotals = $data->fileTotals($file);
            if ($fileTotals['coveredLines'] === $fileTotals['executableLines']) {
                $fullyCovered++;
                continue;
            }
            $incomplete[ReportNames::relativePath($file, $this->baseDirectory)] = $fileTotals;
        }

        if ($incomplete !== []) {
            $width = min(70, max(array_map('strlen', array_keys($incomplete))));
            $output .= PHP_EOL;
            foreach ($incomplete as $file => $fileTotals) {
                $percentage = CoverageData::percentage($fileTotals['coveredLines'], $fileTotals['executableLines']);
                $output .= '  ' . str_pad($file, $width) . '  ' . $this->colored($percentage)
                    . $this->style->dim(" ({$fileTotals['coveredLines']}/{$fileTotals['executableLines']})") . PHP_EOL;
            }
        }
        if ($fullyCovered > 0) {
            $output .= $this->style->dim("  + $fullyCovered file(s) fully covered") . PHP_EOL;
        }

        return $output;
    }

    private function colored(float $percentage): string
    {
        $text = str_pad(number_format($percentage, 1) . '%', 6, ' ', STR_PAD_LEFT);

        return match (true) {
            $percentage >= 80 => $this->style->green($text),
            $percentage >= 50 => $this->style->yellow($text),
            default => $this->style->red($text),
        };
    }
}
