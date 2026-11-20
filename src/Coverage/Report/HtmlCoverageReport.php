<?php

namespace MicroUnit\Coverage\Report;

use MicroUnit\Coverage\CoverageData;
use MicroUnit\Helpers\Filesystem;
use MicroUnit\Helpers\HtmlAssets;
use MicroUnit\MicroUnit;

/** A browsable HTML report: an overview page plus one page per source file. Works offline. */
final readonly class HtmlCoverageReport
{
    public function __construct(
        private string $baseDirectory,
    ) {}

    public function write(CoverageData $data, string $directory): string
    {
        Filesystem::ensureDirectory($directory . '/files');
        $styles = HtmlAssets::baseStyles() . "\n" . HtmlAssets::resource('coverage.css');
        $generatedAt = date('Y-m-d H:i');

        $rows = '';
        foreach (array_keys($data->files) as $file) {
            $page = 'files/' . $this->pageName($file);
            file_put_contents($directory . '/' . $page, $this->filePage($data, $file, $styles, $generatedAt));
            $rows .= $this->fileRow($data, $file, $page);
        }

        $totals = $data->totals();
        $body = $this->readout($totals, count($data->files))
            . '<table class="files"><thead><tr><th class="label">File</th><th class="label">Lines</th>'
            . '<th class="label numbers">Covered</th><th class="label numbers functions-col">Functions</th></tr></thead>'
            . "<tbody>$rows</tbody></table>";

        $index = Filesystem::normalize($directory . '/index.html');
        file_put_contents($index, $this->page('Code coverage', $styles, '<h1>Code coverage</h1>'
            . '<p class="subtitle">' . HtmlAssets::escape($this->baseDirectory) . '</p>' . $body, $generatedAt, ''));

        return $index;
    }

    /** @param array{executableLines: int, coveredLines: int, functions: int, coveredFunctions: int} $totals */
    private function readout(array $totals, ?int $fileCount = null): string
    {
        $lines = CoverageData::percentage($totals['coveredLines'], $totals['executableLines']);
        $functions = CoverageData::percentage($totals['coveredFunctions'], $totals['functions']);

        return '<section class="readout" aria-label="Totals">'
            . $this->readoutValue(number_format($lines, 1) . '<small>%</small>', 'Lines', $this->grade($lines))
            . $this->readoutValue(number_format($functions, 1) . '<small>%</small>', 'Functions', $this->grade($functions))
            . $this->readoutValue((string) $totals['coveredLines'] . '<small>/' . $totals['executableLines'] . '</small>', 'Lines covered', '')
            . ($fileCount === null ? '' : $this->readoutValue((string) $fileCount, 'Files', ''))
            . '</section>';
    }

    private function readoutValue(string $value, string $label, string $grade): string
    {
        return "<div><div class=\"value $grade\">$value</div><span class=\"label\">$label</span></div>";
    }

    private function fileRow(CoverageData $data, string $file, string $page): string
    {
        $totals = $data->fileTotals($file);
        $percentage = CoverageData::percentage($totals['coveredLines'], $totals['executableLines']);
        $relative = $this->relative($file);
        $directory = dirname($relative) === '.' ? '' : dirname($relative) . '/';

        return '<tr>'
            . '<td class="path"><a href="' . HtmlAssets::escape($page) . '"><span class="dir">' . HtmlAssets::escape($directory) . '</span>'
            . HtmlAssets::escape(basename($relative)) . '</a></td>'
            . '<td>' . $this->bar($percentage) . '</td>'
            . "<td class=\"numbers\">{$totals['coveredLines']} / {$totals['executableLines']}</td>"
            . "<td class=\"numbers functions-col\">{$totals['coveredFunctions']} / {$totals['functions']}</td>"
            . '</tr>';
    }

    private function bar(float $percentage): string
    {
        $width = number_format($percentage, 2, '.', '');

        return "<div class=\"bar\"><div class=\"track\"><div class=\"fill\" style=\"width:$width%\"></div></div>"
            . '<span class="percent">' . number_format($percentage, 1) . '%</span></div>';
    }

    private function filePage(CoverageData $data, string $file, string $styles, string $generatedAt): string
    {
        $lines = $data->files[$file]['lines'];
        $source = preg_split('/\R/', (string) @file_get_contents($file)) ?: [];

        $functions = '';
        foreach ($data->functionsOf($file) as $function) {
            $state = $function['isCovered'] ? 'covered' : 'uncovered';
            $name = ReportNames::shortFunctionName($function['name']);
            $functions .= "<a class=\"$state\" href=\"#L{$function['startLine']}\">" . HtmlAssets::escape($name) . '()</a>';
        }

        $rows = '';
        foreach ($source as $index => $code) {
            $number = $index + 1;
            $hits = $lines[$number] ?? null;
            $state = match (true) {
                $hits === null => '',
                $hits > 0 => 'covered',
                default => 'uncovered',
            };
            $hitLabel = match (true) {
                $hits === null => '',
                $hits > 0 => $hits . '×',
                default => '0',
            };
            $rows .= "<tr id=\"L$number\" class=\"$state\"><td class=\"ln\">$number</td><td class=\"hits\">$hitLabel</td>"
                . '<td class="code">' . HtmlAssets::escape($code) . '</td></tr>';
        }

        $totals = $data->fileTotals($file);
        $body = '<h1>' . HtmlAssets::escape(basename($file)) . '</h1>'
            . '<p class="subtitle">' . HtmlAssets::escape($this->relative($file)) . '</p>'
            . $this->readout($totals)
            . ($functions === '' ? '' : "<nav class=\"functions\" aria-label=\"Functions\">$functions</nav>")
            . '<p class="legend"><span class="covered">Executed</span><span class="uncovered">Not executed</span></p>'
            . "<div class=\"source\"><table>$rows</table></div>";

        return $this->page(basename($file) . ' · Code coverage', $styles, $body, $generatedAt, '../');
    }

    private function page(string $title, string $styles, string $body, string $generatedAt, string $root): string
    {
        return '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>' . HtmlAssets::escape($title) . '</title>'
            . '<link rel="icon" href="' . HtmlAssets::faviconDataUri() . '">'
            . "<style>$styles</style></head><body>"
            . '<header class="top"><a class="brand" href="' . $root . 'index.html">' . HtmlAssets::mark()
            . '<div><div class="wordmark">microunit</div><div class="label">Code coverage</div></div></a>'
            . '<span class="label">Generated ' . HtmlAssets::escape($generatedAt) . '</span></header>'
            . "<main>$body<footer>MicroUnit " . MicroUnit::VERSION . ' · statement coverage measured in pure PHP</footer></main>'
            . '</body></html>';
    }

    private function grade(float $percentage): string
    {
        return match (true) {
            $percentage >= 80 => 'good',
            $percentage >= 50 => 'fair',
            default => 'poor',
        };
    }

    private function pageName(string $file): string
    {
        return trim(preg_replace('/[^A-Za-z0-9]+/', '-', $this->relative($file)) ?? 'file', '-') . '.html';
    }

    private function relative(string $file): string
    {
        return ReportNames::relativePath($file, $this->baseDirectory);
    }
}
