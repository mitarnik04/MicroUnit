<?php

/**
 * Renders the raster brand assets from their HTML/SVG sources with headless Chrome.
 *
 *     php assets/brand/build.php              every asset
 *     php assets/brand/build.php benchmarks   only the assets whose output path contains "benchmarks"
 *
 * Maintainer tooling only (needs Chrome or Edge; set CHROME_BIN to override). The SVGs in this
 * folder are the masters; every PNG here is generated and can be rebuilt at any time.
 */

const BRAND_DIR = __DIR__;

/** source => [output, width, height, scale, transparent] */
const ASSETS = [
    'src/banner-light.html' => ['banner-light.png', 1280, 400, 2, false],
    'src/banner-dark.html' => ['banner-dark.png', 1280, 400, 2, false],
    'src/social-preview.html' => ['social-preview.png', 1280, 640, 1, false],
    'src/release-1.0.html' => ['release-1.0.png', 1080, 1080, 2, false],
    'src/release-1.0-features.html' => ['release-1.0-features.png', 1080, 1080, 2, false],
    'src/lockup.html' => ['lockup.png', 1200, 240, 2, true],
    'src/lockup-inverse.html' => ['lockup-inverse.png', 1200, 240, 2, true],
    'favicon.svg' => ['favicon-32.png', 32, 32, 1, true],
    'app-icon.svg' => ['apple-touch-icon.png', 180, 180, 1, true],
];

$chrome = findChrome() ?? exit("Chrome or Edge not found. Set CHROME_BIN to its executable.\n");
$profile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'microunit-brand-chrome';

$only = $argv[1] ?? '';

foreach ([...ASSETS, ...benchmarkAssets()] as $source => [$output, $width, $height, $scale, $isTransparent]) {
    if (!str_contains($output, $only)) {
        continue;
    }
    $sourcePath = BRAND_DIR . '/' . $source;
    $outputPath = BRAND_DIR . '/' . $output;
    if (!is_dir(dirname($outputPath))) {
        mkdir(dirname($outputPath), recursive: true);
    }
    $url = str_ends_with($source, '.svg') ? svgPage($sourcePath, $width, $height) : 'file:///' . str_replace('\\', '/', $sourcePath);

    $command = [
        $chrome,
        '--headless=new',
        '--disable-gpu',
        '--hide-scrollbars',
        '--no-first-run',
        '--user-data-dir=' . $profile,
        "--window-size=$width,$height",
        "--force-device-scale-factor=$scale",
        '--virtual-time-budget=5000',
        '--allow-file-access-from-files', // the benchmark page reads the published results (JSON)
        '--screenshot=' . $outputPath,
    ];
    if ($isTransparent) {
        $command[] = '--default-background-color=00000000';
    }
    $command[] = $url;

    $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    stream_get_contents($pipes[1]);
    stream_get_contents($pipes[2]);
    proc_close($process);

    echo is_file($outputPath) ? "✔ $output ({$width}×{$height} @{$scale}x)\n" : "✖ $output failed\n";
}

/**
 * The benchmark charts and tables, each light and dark, rendered from src/benchmark.html with the numbers of
 * the published results. Heights are the pages' content heights.
 *
 * @return array<string, array{string, int, int, int, bool}>
 */
function benchmarkAssets(): array
{
    $heights = ['overview' => 1494, 'suite' => 910, 'parallel' => 910, 'file' => 910, 'startup' => 910];
    foreach (['suite', 'parallel', 'file', 'startup'] as $scenario) {
        $heights["table-$scenario"] = 1142;
    }

    $assets = [];
    foreach ($heights as $asset => $height) {
        foreach (['light', 'dark'] as $theme) {
            $assets["src/benchmark.html#$asset-$theme"] = ["benchmarks/$asset-$theme.png", 1280, $height, 2, false];
        }
    }

    return $assets;
}

function findChrome(): ?string
{
    $candidates = array_filter([
        getenv('CHROME_BIN') ?: null,
        'C:/Program Files/Google/Chrome/Application/chrome.exe',
        'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe',
        '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
        '/usr/bin/google-chrome',
        '/usr/bin/chromium',
        '/usr/bin/chromium-browser',
    ]);

    return array_find($candidates, static fn(string $path): bool => is_file($path));
}

/** Wraps an SVG in a page that renders it edge to edge at the requested size. */
function svgPage(string $svgPath, int $width, int $height): string
{
    $html = sprintf(
        '<!doctype html><html><body style="margin:0;width:%dpx;height:%dpx;overflow:hidden;background:transparent">'
        . '<img src="data:image/svg+xml;base64,%s" width="%d" height="%d" style="display:block"></body></html>',
        $width,
        $height,
        base64_encode((string) file_get_contents($svgPath)),
        $width,
        $height,
    );

    return 'data:text/html;base64,' . base64_encode($html);
}
