<?php

/**
 * MicroUnit benchmark: PHPUnit (and ParaTest for parallel runs) and MicroUnit on the same real-world test suites.
 *
 *     php benchmarks/run.php --work=DIR [--php=PHP] [--runs=5] [--warmup=1] [--workers=4]
 *                            [--projects=math,yaml] [--scenarios=suite,parallel,file,startup]
 *
 * --work is the directory with the corpus checked out (see benchmarks/README.md for the setup).
 * Results go to benchmarks/results/<date>/. Zero dependencies, like MicroUnit itself.
 */

declare(strict_types=1);

spl_autoload_register(static function (string $class): void {
    if (str_starts_with($class, 'MicroUnit\\Benchmarks\\')) {
        require __DIR__ . '/src/' . substr($class, strlen('MicroUnit\\Benchmarks\\')) . '.php';
    }
});

use MicroUnit\Benchmarks\Benchmark;
use MicroUnit\Benchmarks\Environment;
use MicroUnit\Benchmarks\Report;

$arguments = getopt('', ['work:', 'php:', 'runs:', 'warmup:', 'workers:', 'projects:', 'scenarios:', 'out:']);
if (!isset($arguments['work'])) {
    fwrite(STDERR, "Usage: php benchmarks/run.php --work=DIR [--php=PHP] [--runs=5] [--warmup=1] [--workers=4] [--projects=a,b] [--scenarios=suite,parallel,file,startup]\n");
    exit(2);
}

$corpus = json_decode((string) file_get_contents(__DIR__ . '/corpus.json'), true, flags: JSON_THROW_ON_ERROR);
$selected = isset($arguments['projects']) ? explode(',', $arguments['projects']) : array_column($corpus['projects'], 'slug');
$options = [
    'php' => $arguments['php'] ?? PHP_BINARY,
    'microunit' => realpath(__DIR__ . '/../bin/microunit'),
    'work' => rtrim(str_replace('\\', '/', (string) realpath($arguments['work'])), '/'),
    'runs' => (int) ($arguments['runs'] ?? 5),
    'warmup' => (int) ($arguments['warmup'] ?? 1),
    'workers' => (int) ($arguments['workers'] ?? 4),
    'scenarios' => isset($arguments['scenarios']) ? explode(',', $arguments['scenarios']) : array_keys(Benchmark::SCENARIOS),
];
$options['out'] = $arguments['out'] ?? __DIR__ . '/results/' . date('Y-m-d') . (count($selected) < count($corpus['projects']) ? '-partial' : '');
@mkdir($options['out'] . '/excluded', 0777, true);

$started = microtime(true);
$environment = Environment::capture($options['php']);
file_put_contents($options['out'] . '/environment.json', json_encode($environment, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
$log = static fn(string $message): int => fwrite(STDOUT, '[' . date('H:i:s') . "] $message\n");

$results = [];
$runs = fopen($options['out'] . '/runs.jsonl', 'w');
foreach ($corpus['projects'] as $project) {
    if (!in_array($project['slug'], $selected, true)) {
        continue;
    }
    $log("== {$project['name']} {$project['tag']}");
    $benchmark = new Benchmark($project, $options, static fn(string $message) => $log("  $message"));
    $benchmark->run();
    foreach ($benchmark->runs as $run) {
        fwrite($runs, json_encode($run) . "\n");
    }
    file_put_contents($options['out'] . "/excluded/{$project['slug']}.json", json_encode($benchmark->excluded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    $results[$project['slug']] = ['project' => $project, 'summary' => $benchmark->summary, 'excluded' => $benchmark->excluded, 'versions' => $benchmark->versions];
}
fclose($runs);

$environment['durationSeconds'] = round(microtime(true) - $started);
file_put_contents($options['out'] . '/environment.json', json_encode($environment, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
file_put_contents($options['out'] . '/summary.json', json_encode(['options' => array_diff_key($options, ['php' => 0, 'microunit' => 0, 'work' => 0, 'out' => 0]), 'results' => $results], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
file_put_contents($options['out'] . '/summary.md', Report::markdown($environment, $results, $options));
@unlink($options['out'] . '/memory.tmp');
$log('Done: ' . $options['out'] . '/summary.md');
