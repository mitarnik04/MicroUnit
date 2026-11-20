<?php

namespace MicroUnit\Benchmarks;

/**
 * Runs the benchmark for one project:
 *
 * 1. checks that the project is checked out at the pinned commit;
 * 2. migrates its tests with `microunit migrate` (the MicroUnit side is generated, never hand-edited);
 * 3. builds the identical test set (see Selection) and verifies that every tool runs the same
 *    number of tests; a scenario whose counts differ is reported as invalid, never as a result;
 * 4. measures every scenario: warm-up runs (discarded), then rounds in which every tool runs once,
 *    in a rotating order so no tool always runs first or last.
 */
final class Benchmark
{
    public const array SCENARIOS = [
        'suite' => 'Full suite, sequential',
        'parallel' => 'Full suite, parallel',
        'file' => 'One test file (the edit-and-rerun loop)',
        'startup' => 'Startup: no test selected',
    ];

    /** @var list<array<string, mixed>> every measured run */
    public array $runs = [];

    /** @var array<string, mixed> per scenario and tool: statistics, test counts, validity */
    public array $summary = [];

    /** @var array<string, string> */
    public array $excluded = [];

    /** @var array<string, string> tool => version line */
    public array $versions = [];

    /**
     * @param array<string, mixed> $project entry of corpus.json
     * @param array{php: string, microunit: string, work: string, runs: int, warmup: int, workers: int, scenarios: list<string>, out: string} $options
     */
    public function __construct(
        private readonly array $project,
        private readonly array $options,
        private readonly \Closure $log,
    ) {}

    public function run(): void
    {
        $directory = $this->directory('.');
        $this->verifyCommit($directory);
        ($this->log)('migrating with microunit migrate');
        $this->execute(['{microunit}', 'migrate', $this->project['tests'], '--out=tests-microunit'], '.', check: true);

        foreach ($this->project['tools'] as $tool => $definition) {
            $version = $this->execute([$definition['command'][0], '--version'], $definition['dir'])->output;
            $this->versions[$tool] = trim((string) array_find(explode("
", (string) preg_replace('/\[[0-9;]*m/', '', $version)), static fn(string $line): bool => trim($line) !== ''));
        }

        $selection = $this->selection($directory);
        $this->excluded = $selection->excluded();

        foreach ($this->options['scenarios'] as $scenario) {
            $commands = $this->commands($scenario, $selection);
            if (count($commands) < 2) {
                ($this->log)("$scenario: skipped, no other tool to compare with in this project");
                continue;
            }
            ($this->log)("$scenario: verifying test counts");
            $counts = [];
            $failedTools = [];
            foreach ($commands as $tool => [$dir, $command]) {
                // PHPUnit and ParaTest are counted from their JUnit report, MicroUnit from its summary line.
                $junit = $tool === 'microunit' || $scenario === 'startup' ? null : $this->options['out'] . "/junit/{$this->project['slug']}-$scenario-$tool.xml";
                @mkdir($this->options['out'] . '/junit', 0777, true);
                $execution = $this->execute([...$command, ...($junit === null ? [] : ["--log-junit=$junit"])], $dir, saveAs: "$scenario-$tool");
                $counts[$tool] = ($junit === null ? null : Execution::junitTestCount($junit)) ?? $execution->tests;
                // PHPUnit and ParaTest are judged by their JUnit report, not the exit code: PHPUnit also exits
                // non-zero for problems outside the tests (an empty data provider on this platform).
                // Startup selects no test on purpose.
                $problems = $junit === null ? ($execution->exitCode === 0 ? 0 : "exit code {$execution->exitCode}") : (Execution::junitProblems($junit) ?? 'no JUnit report');
                if ($problems !== 0 && $scenario !== 'startup') {
                    $failedTools[$tool] = is_int($problems) ? "$problems failed tests" : $problems;
                }
            }
            $isSameCount = $scenario === 'startup' || count(array_unique(array_filter($counts, static fn(?int $count): bool => $count !== null))) === 1 && !in_array(null, $counts, true);
            // Every remaining test passes under every tool, so a non-zero exit means the run is broken, not slow.
            $isValid = $isSameCount && $failedTools === [];
            $this->summary[$scenario] = ['valid' => $isValid, 'testCounts' => $counts, 'problems' => $failedTools, 'tools' => []];
            if (!$isValid) {
                ($this->log)("$scenario: INVALID, " . ($isSameCount ? 'a verification run failed: ' . json_encode($failedTools) : 'the tools ran different numbers of tests: ' . json_encode($counts)));
                continue;
            }

            $tools = array_keys($commands);
            for ($round = -$this->options['warmup']; $round < $this->options['runs']; $round++) {
                $offset = (($round % count($tools)) + count($tools)) % count($tools);
                $order = [...array_slice($tools, $offset), ...array_slice($tools, 0, $offset)];
                foreach ($order as $position => $tool) {
                    [$dir, $command] = $commands[$tool];
                    $execution = $this->execute($command, $dir);
                    if ($round < 0) {
                        continue; // warm-up
                    }
                    $this->runs[] = [
                        'project' => $this->project['name'], 'scenario' => $scenario, 'tool' => $tool, 'round' => $round + 1,
                        'positionInRound' => $position + 1, 'seconds' => round($execution->seconds, 6), 'exitCode' => $execution->exitCode,
                        'peakMemoryBytes' => $execution->peakMemoryBytes, 'tests' => $execution->tests,
                    ];
                }
                ($this->log)(sprintf('%s: %s %d/%d', $scenario, $round < 0 ? 'warm-up' : 'round', $round < 0 ? $round + $this->options['warmup'] + 1 : $round + 1, $round < 0 ? $this->options['warmup'] : $this->options['runs']));
            }
            foreach ($tools as $tool) {
                $runs = array_values(array_filter($this->runs, fn(array $run): bool => $run['scenario'] === $scenario && $run['tool'] === $tool && $run['project'] === $this->project['name']));
                $memory = array_values(array_filter(array_column($runs, 'peakMemoryBytes')));
                $this->summary[$scenario]['tools'][$tool] = [
                    'seconds' => Statistics::of(array_column($runs, 'seconds'))->toArray(),
                    'peakMemoryMiB' => $memory === [] ? null : Statistics::of(array_map(static fn(int $bytes): float => $bytes / 1048576, $memory))->toArray(),
                    'exitCodes' => array_values(array_unique(array_column($runs, 'exitCode'))),
                    'command' => implode(' ', array_map(fn(string $part): string => $this->display($part), $commands[$tool][1])),
                ];
            }
        }
    }

    /** Builds the identical test set, excluding what fails under PHPUnit or after migration. */
    private function selection(string $directory): Selection
    {
        $report = json_decode((string) file_get_contents("$directory/tests-microunit/migration-report.json"), true);
        $selection = new Selection($report);
        foreach ($this->project['excludeClasses'] ?? [] as $class => $reason) {
            $selection->exclude($class, $reason);
        }

        ($this->log)('reference run: PHPUnit (to exclude tests that fail on this machine)');
        $junit = $this->options['out'] . '/' . $this->project['slug'] . '-phpunit-junit.xml';
        $phpunit = $this->project['tools']['phpunit'];
        $filter = $selection->phpUnitFilter();
        $this->execute([...$phpunit['command'], ...($filter === null ? [] : ["--filter=$filter"]), "--log-junit=$junit"], $phpunit['dir']);
        $selection->learnPhpUnitDataSets($junit);
        $failures = $selection->excludeJUnitFailures($junit, 'fails under PHPUnit on this machine');
        ($this->log)("  $failures failing PHPUnit tests excluded");

        ($this->log)('reference run: MicroUnit (to exclude tests whose migration fails)');
        $listing = $this->execute([...$this->project['tools']['microunit']['command'], '--list=json'], '.')->output;
        $selection->learnMicroUnitTests(array_values(array_filter(array_map(static fn(string $line): mixed => json_decode($line, true), explode("\n", $listing)), 'is_array')));
        $mismatches = $selection->excludeDataSetMismatches('a different number of tests');
        ($this->log)("  $mismatches methods with a different number of data sets excluded");
        $output = $this->execute([...$this->project['tools']['microunit']['command'], ...$this->microUnitFilterArgument($selection)], '.')->output;
        $failed = self::failedMicroUnitTests($output);
        $selection->excludeMicroUnitFailures($failed, 'fails after migration (a migrate limitation)');
        ($this->log)('  ' . count($failed) . ' failing migrated tests excluded');

        // Tests that only pass in one order: ParaTest distributes classes differently than a
        // sequential run, so they fail there. Excluded everywhere, so every scenario keeps one test set.
        $paratest = $this->project['tools']['paratest'] ?? null;
        if ($paratest !== null && in_array('parallel', $this->options['scenarios'], true)) {
            ($this->log)('reference run: ParaTest (to exclude tests that depend on the test order)');
            $parallelJunit = $this->options['out'] . '/' . $this->project['slug'] . '-paratest-junit.xml';
            $workers = (string) $this->options['workers'];
            $command = array_map(static fn(string $part): string => str_replace('{workers}', $workers, $part), $paratest['command']);
            $filter = $selection->phpUnitFilter();
            $this->execute([...$command, ...($filter === null ? [] : ["--filter=$filter"]), "--log-junit=$parallelJunit"], $paratest['dir']);
            $orderDependent = $selection->excludeJUnitFailures($parallelJunit, 'fails when run in parallel under ParaTest (depends on the test order)');
            ($this->log)("  $orderDependent order-dependent tests excluded");
        }

        return $selection;
    }

    /**
     * Command per tool for a scenario: [directory, argv].
     *
     * @return array<string, array{string, list<string>}>
     */
    private function commands(string $scenario, Selection $selection): array
    {
        $tools = $this->project['tools'];
        $phpUnitFilter = $selection->phpUnitFilter();
        $filtered = static fn(array $command, ?string $filter): array => [...$command, ...($filter === null ? [] : ["--filter=$filter"])];
        $microUnitFilter = $this->microUnitFilterArgument($selection);
        $none = '--filter=/^no_test_has_this_name$/';
        $workers = (string) $this->options['workers'];
        $withWorkers = static fn(array $arguments): array => array_map(static fn(string $part): string => str_replace('{workers}', $workers, $part), $arguments);

        $commands = [];
        foreach (['phpunit', 'microunit'] as $tool) {
            if (!isset($tools[$tool])) {
                continue;
            }
            $base = $tools[$tool]['command'];
            $commands[$tool] = match ($scenario) {
                'suite' => [$tools[$tool]['dir'], $tool === 'microunit' ? [...$base, ...$microUnitFilter] : $filtered($base, $phpUnitFilter)],
                'parallel' => match ($tool) {
                    'phpunit' => isset($tools['paratest']) ? [$tools['paratest']['dir'], $withWorkers($filtered($tools['paratest']['command'], $phpUnitFilter))] : null,
                    default => [$tools[$tool]['dir'], [...$base, ...$microUnitFilter, "--parallel=$workers"]],
                },
                'file' => [$tools[$tool]['dir'], $tool === 'microunit'
                    ? [...$base, ...$microUnitFilter, 'tests-microunit/' . substr($this->project['file'], strlen($this->project['tests']) + 1)]
                    : [...$filtered($base, $phpUnitFilter), $this->project['file']]],
                'startup' => [$tools[$tool]['dir'], [...$base, $none]],
                default => null,
            };
            if ($commands[$tool] === null) {
                unset($commands[$tool]);
            }
        }
        if ($scenario === 'parallel' && isset($commands['phpunit'])) {
            $commands = ['paratest' => $commands['phpunit'], ...array_diff_key($commands, ['phpunit' => true])];
        }

        return $commands;
    }

    /** @return list<string> */
    private function microUnitFilterArgument(Selection $selection): array
    {
        $filter = $selection->microUnitFilter();

        return $filter === null ? [] : ["--filter=$filter"];
    }

    /** @return list<string> ids of the failed tests in MicroUnit's console output */
    private static function failedMicroUnitTests(string $output): array
    {
        $failed = [];
        $suite = null;
        foreach (explode("\n", str_replace("\r", '', $output)) as $line) {
            if (preg_match('/^-- (.+) --$/', $line, $match)) {
                $suite = $match[1];
            } elseif ($suite !== null && preg_match('/^\[✖\] (.+?) \(\d/', $line, $match)) {
                $failed[] = "$suite::{$match[1]}";
            }
        }

        return $failed;
    }

    /** @param list<string> $command */
    private function execute(array $command, string $directory, bool $check = false, ?string $saveAs = null): Execution
    {
        $argv = [$this->options['php'], '-d', 'auto_prepend_file=' . __DIR__ . '/../memory-probe.php', ...($this->project['phpArguments'] ?? [])];
        foreach ($command as $part) {
            $argv[] = $part === '{microunit}' ? $this->options['microunit'] : $part;
        }
        $execution = Execution::run($argv, $this->directory($directory), $this->project['environment'] ?? [], $this->options['out'] . '/memory.tmp');
        if ($saveAs !== null) {
            $file = $this->options['out'] . '/outputs/' . $this->project['slug'] . "/$saveAs.txt";
            @mkdir(dirname($file), 0777, true);
            file_put_contents($file, '$ ' . implode(' ', array_map($this->display(...), $command)) . "\n\n" . $execution->output);
        }
        if ($check && $execution->exitCode !== 0) {
            throw new \RuntimeException("Command failed ({$execution->exitCode}): " . implode(' ', $command) . "\n" . substr($execution->output, -2000));
        }

        return $execution;
    }

    private function verifyCommit(string $directory): void
    {
        $head = trim((string) shell_exec('git -C ' . escapeshellarg($directory) . ' rev-parse HEAD'));
        if ($head !== $this->project['sha']) {
            throw new \RuntimeException("{$this->project['name']} is at $head, not at the pinned commit {$this->project['sha']}.");
        }
    }

    private function directory(string $relative): string
    {
        return $this->options['work'] . '/' . $this->project['slug'] . ($relative === '.' ? '' : '/' . $relative);
    }

    /** Command parts as published: no local absolute paths. */
    private function display(string $part): string
    {
        return $part === '{microunit}' ? 'vendor/bin/microunit' : (strlen($part) > 160 ? substr($part, 0, 157) . '...' : $part);
    }
}
