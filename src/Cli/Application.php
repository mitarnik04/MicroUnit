<?php

namespace MicroUnit\Cli;

use MicroUnit\Bootstrap\ConfigLoader;
use MicroUnit\Bootstrap\ConfigurationException;
use MicroUnit\Bootstrap\LoadedConfig;
use MicroUnit\Bootstrap\ProjectAutoloader;
use MicroUnit\Bootstrap\RunLog;
use MicroUnit\Bootstrap\RuntimeDirectory;
use MicroUnit\Config\CoverageConfig;
use MicroUnit\Config\MicroUnitConfig;
use MicroUnit\Config\TestGroup;
use MicroUnit\Core\LastRun;
use MicroUnit\Core\RunSummary;
use MicroUnit\Core\SuiteRunner;
use MicroUnit\Core\TestFilter;
use MicroUnit\Core\TestPlan;
use MicroUnit\Core\TestRegistry;
use MicroUnit\Core\TestResult;
use MicroUnit\Core\TestRunner;
use MicroUnit\Coverage\CoverageReport;
use MicroUnit\Coverage\CoverageReporter;
use MicroUnit\Coverage\CoverageSession;
use MicroUnit\Coverage\SourceSelection;
use MicroUnit\Explorer\ExplorerLauncher;
use MicroUnit\Explorer\ExplorerReport;
use MicroUnit\Explorer\ExplorerServer;
use MicroUnit\Explorer\ExplorerState;
use MicroUnit\Helpers\Filesystem;
use MicroUnit\Helpers\Glob;
use MicroUnit\Helpers\PhpProcess;
use MicroUnit\Init\InitCommand;
use MicroUnit\Integrity\IntegrityCheck;
use MicroUnit\MicroUnit;
use MicroUnit\Migrate\MigrateCommand;
use MicroUnit\Output\AgentTestWriter;
use MicroUnit\Output\Format\TextStyle;
use MicroUnit\Output\GitHubActionsTestWriter;
use MicroUnit\Output\ITestWriter;
use MicroUnit\Output\MinimalStringTestWriter;
use MicroUnit\Output\StringTestWriter;
use MicroUnit\Parallel\ParallelRunner;
use MicroUnit\Parallel\WorkerRunner;

/** One invocation of the `microunit` command. */
final class Application
{
    public const int EXIT_SUCCESS = 0;

    public const int EXIT_FAILURE = 1;

    public const int EXIT_USAGE_ERROR = 2;

    /** Below this run time (of the previous run) automatic parallelism is not worth starting workers. */
    private const float PARALLEL_WORTH_SECONDS = 1.0;

    public function __construct(
        private readonly string $entryScript,
        private readonly string $workingDirectory,
    ) {}

    /** @param list<string> $arguments argv without the script name */
    public function run(array $arguments): int
    {
        try {
            $options = CliOptions::parse($arguments);
            $this->applyColorPreference($options->colors);
            if ($options->updateSnapshots) {
                putenv('MICROUNIT_UPDATE_SNAPSHOTS=1'); // inherited by parallel workers
            }

            $exitCode = match ($options->command) {
                'help' => $this->printHelp($options->helpTopic ?? $options->paths[0] ?? null),
                'version' => $this->printVersion(),
                'init' => new InitCommand($this->workingDirectory, $options->installAgentSkill)->run(),
                'migrate' => new MigrateCommand($this->workingDirectory, $options->paths, $options->outputDirectory ?? MigrateCommand::DEFAULT_OUTPUT)->run(),
                default => $this->runWithConfig($options),
            };
            TestRegistry::release();

            return $exitCode;
        } catch (UsageException | ConfigurationException $exception) {
            fwrite(STDERR, $exception->getMessage() . PHP_EOL);
            return self::EXIT_USAGE_ERROR;
        }
    }

    private function runWithConfig(CliOptions $options): int
    {
        ProjectAutoloader::prepare($this->workingDirectory, dirname(__DIR__, 2));
        $loaded = new ConfigLoader()->load($this->workingDirectory, $options->configFile);
        $runtime = new RuntimeDirectory($loaded->resolve($loaded->config->runtimeDirectory));

        if ($options->command === 'explorer-server') {
            return new ExplorerServer($this->explorerState($loaded, $runtime), $options->explorerPort ?? $loaded->config->testExplorerPort, $this->runnerCommand($loaded))->serve();
        }
        if ($options->command === 'explorer') {
            return $this->explorerLauncher($loaded, $this->explorerState($loaded, $runtime))->open() ? self::EXIT_SUCCESS : self::EXIT_FAILURE;
        }
        if ($options->command === 'integrity') {
            return $this->checkIntegrity($loaded, $options->paths[0] ?? 'HEAD');
        }

        // Parallel workers keep earlier logs: the parent's log of this very run is among them.
        RunLog::start($runtime->logs(), $loaded->config->persistRunLogs || $options->worker !== null);

        $coverage = $this->startCoverage($loaded, $options, $runtime);
        ProjectAutoloader::load();
        $loaded->config->framework?->boot($loaded->baseDirectory);
        $this->loadTestFiles($loaded, $options);

        $lastRun = new LastRun($runtime->lastRunFile());
        $plan = TestPlan::create(TestRegistry::instance()->testers(), $this->filterFor($options, $lastRun));
        if ($options->list !== null) {
            $this->listTests($plan, $options->list);

            return self::EXIT_SUCCESS;
        }
        $testRunner = new TestRunner($loaded->config->framework === null ? [] : [$loaded->config->framework]);
        $stopOnFailure = $options->stopOnFailure || $loaded->config->stopOnFailure;

        if ($options->worker !== null) {
            new WorkerRunner($testRunner, $stopOnFailure)->run($plan->partition($options->worker->index, $options->worker->count), $options->worker->outputFile, $coverage);
            return self::EXIT_SUCCESS;
        }

        return $this->runTests($loaded, $options, $plan, $testRunner, $stopOnFailure, $coverage, $runtime, $lastRun);
    }

    private function runTests(
        LoadedConfig $loaded,
        CliOptions $options,
        TestPlan $plan,
        TestRunner $testRunner,
        bool $stopOnFailure,
        ?CoverageSession $coverage,
        RuntimeDirectory $runtime,
        LastRun $lastRun,
    ): int {
        $isAgentSession = $options->agent ?? $loaded->config->agentOutput ?? AgentTestWriter::isAgentSession();
        $integrity = $this->integrityFindings($loaded);
        $checkFailures = [];
        $writers = $this->writersFor($loaded, $isAgentSession, $integrity, static function () use (&$checkFailures): array {
            return $checkFailures;
        });
        $onSuiteFinished = static function (string $suite, array $results) use ($writers): void {
            foreach ($writers as $writer) {
                $writer->writeSuite($suite);
                /** @var list<TestResult> $results as SuiteRunner and ParallelRunner pass them */
                $writer->writeResults($results);
            }
        };

        $workerCount = $this->workerCount($options, $loaded, $plan, $lastRun);
        if ($workerCount > 1) {
            $outcome = new ParallelRunner($this->workerCommand($loaded, $options, $coverage !== null, $runtime), $workerCount, $runtime->temporary())
                ->run($plan, $onSuiteFinished);
            $summary = $outcome->summary;
            $coverageData = $outcome->coverage;
        } else {
            $summary = new SuiteRunner($testRunner, $stopOnFailure)->run($plan, $onSuiteFinished);
            $coverageData = $coverage?->collect();
        }

        // People read the summary before the coverage table; an agent's single JSON object (the
        // summary) must already know every reason the run fails, so its checks come first.
        $checks = function () use ($loaded, $summary, $isAgentSession, $integrity, $coverageData): array {
            $stream = $isAgentSession ? STDERR : STDOUT;
            $coverage = $coverageData === null ? null : new CoverageReporter($loaded, TextStyle::forStream($stream), $stream)->report($coverageData, $loaded->config->coverage ?? new CoverageConfig());

            return [$coverage, $this->checkFailures($loaded, $summary, $isAgentSession, $integrity, $coverage)];
        };
        if ($isAgentSession) {
            [$coverage, $checkFailures] = $checks();
        }
        foreach ($writers as $writer) {
            $writer->writeSummary($summary->total, $summary->passed, $summary->failed);
        }
        if (!$isAgentSession) {
            [$coverage, $checkFailures] = $checks();
        }

        $lastRun->save($summary);
        $this->publishToExplorer($loaded, $options, $plan, $summary, $runtime, $coverage?->summary);

        return $summary->hasFailures || $checkFailures !== [] ? self::EXIT_FAILURE : self::EXIT_SUCCESS;
    }

    /**
     * Why the run fails although no test failed: a coverage minimum or a strict check the
     * configuration enabled. Outside agent sessions the integrity findings and risky tests are
     * printed too (agents get them in their JSON).
     *
     * @param (\Closure(): list<array{test: string, file: string, line: int, finding: string}>)|null $integrity
     * @return list<string>
     */
    private function checkFailures(LoadedConfig $loaded, RunSummary $summary, bool $isAgentSession, ?\Closure $integrity, ?CoverageReport $coverage): array
    {
        $config = $loaded->config;
        $stream = $isAgentSession ? STDERR : STDOUT;
        $style = TextStyle::forStream($stream);
        $failures = [];

        if ($coverage?->isBelowMinimum) {
            $failures[] = sprintf('line coverage %.1f%% is below the minimum of %.1f%%', $coverage->summary['lineRate'], $config->coverage?->minimumLineCoverage);
        }

        $findings = $integrity === null ? [] : $integrity();
        if ($findings !== [] && !$isAgentSession) {
            fwrite($stream, PHP_EOL . $style->yellow(count($findings) . ' test(s) weakened compared to ' . $config->integrityCheck . ':') . PHP_EOL);
            foreach ($findings as $finding) {
                fwrite($stream, "  {$finding['file']}:{$finding['line']} {$finding['test']}: {$finding['finding']}" . PHP_EOL);
            }
        }
        if ($config->failOnWeakenedTests && $config->integrityCheck !== null) {
            if ($findings !== []) {
                $failures[] = count($findings) . " test(s) weakened compared to {$config->integrityCheck} (failOnFindings)";
            } elseif (!new IntegrityCheck($loaded->baseDirectory, $config->integrityCheck)->isAvailable()) {
                // failOnFindings must not pass because the comparison could not run (shallow clone, no git).
                $failures[] = "the integrity check could not compare with {$config->integrityCheck} (failOnFindings)";
            }
        }

        $risky = array_filter($summary->results, static fn(TestResult $result): bool => $result->isRisky());
        if ($risky !== [] && $config->failOnRiskyTests) {
            $failures[] = count($risky) . ' test(s) passed without a single assertion (failOnRiskyTests)';
            if (!$isAgentSession) {
                fwrite($stream, $style->red(count($risky) . ' test(s) passed without a single assertion (failOnRiskyTests).') . PHP_EOL);
            }
        }

        return $failures;
    }

    /**
     * The integrity check for this run, if configured (it is opt-in). Computed at most once.
     *
     * @return (\Closure(): list<array{test: string, file: string, line: int, finding: string}>)|null
     */
    private function integrityFindings(LoadedConfig $loaded): ?\Closure
    {
        $revision = $loaded->config->integrityCheck;
        if ($revision === null) {
            return null;
        }
        $check = new IntegrityCheck($loaded->baseDirectory, $revision);
        $directories = $this->testDirectories($loaded);
        $findings = null;

        return static function () use ($check, $directories, $revision, &$findings): array {
            if ($findings === null && !$check->isAvailable()) {
                fwrite(STDERR, "The integrity check could not run: no git repository or no revision '$revision' (in CI, fetch the history, e.g. fetch-depth: 0)." . PHP_EOL);

                return $findings = [];
            }

            return $findings ??= $check->findings($directories);
        };
    }

    private function startCoverage(LoadedConfig $loaded, CliOptions $options, RuntimeDirectory $runtime): ?CoverageSession
    {
        $isEnabled = $options->coverage ?? $loaded->config->coverage !== null;
        if (!$isEnabled) {
            return null;
        }

        $coverageConfig = $loaded->config->coverage ?? new CoverageConfig();
        $sources = new SourceSelection(
            array_map($loaded->resolve(...), $coverageConfig->include),
            array_map($loaded->resolve(...), $coverageConfig->exclude),
        );

        return CoverageSession::start($sources, $runtime->coverageCache());
    }

    private function loadTestFiles(LoadedConfig $loaded, CliOptions $options): void
    {
        // Each file gets its own scope: no $this, no variables of the runner or of other test files.
        $require = static function (string $file): void {
            require_once $file;
        };
        $config = $loaded->config;
        if ($config->bootstrapFile !== null) {
            $bootstrap = $loaded->resolve($config->bootstrapFile);
            if (!is_file($bootstrap)) {
                throw new ConfigurationException("The bootstrap file '$bootstrap' does not exist.");
            }
            $require($bootstrap);
        }

        foreach ($this->discoverTestFiles($loaded, $options) as $testFile) {
            $require($testFile);
        }
    }

    /** @return list<string> */
    private function discoverTestFiles(LoadedConfig $loaded, CliOptions $options): array
    {
        $config = $loaded->config;
        $defaultRegexes = array_map(Glob::toRegex(...), $config->testFilePatterns);

        if ($options->paths !== []) {
            $files = [];
            foreach ($options->paths as $path) {
                $resolved = Filesystem::resolve($this->workingDirectory, $path);
                if (!file_exists($resolved)) {
                    throw new UsageException("The path '$path' does not exist.");
                }
                $files = [...$files, ...Filesystem::findFiles($resolved, $defaultRegexes)];
            }
            return array_values(array_unique($files));
        }

        $sources = [];
        $runsEverything = $options->groups === [];

        // The plain test directory belongs to no group: it runs when no --group is selected.
        if ($runsEverything && ($config->testDirectory !== null || $config->testGroups === [])) {
            $sources[] = [$config->testDirectory ?? MicroUnitConfig::DEFAULT_TEST_DIRECTORY, $defaultRegexes];
        }

        $groupNames = array_map(static fn(TestGroup $group): string => $group->name, $config->testGroups);
        $unknownGroups = array_diff($options->groups, $groupNames);
        if ($unknownGroups !== []) {
            throw new UsageException('Unknown test group: ' . implode(', ', $unknownGroups) . '. Configured groups: ' . (implode(', ', $groupNames) ?: 'none') . '.');
        }

        foreach ($config->testGroups as $group) {
            if ($runsEverything || in_array($group->name, $options->groups, true)) {
                $regexes = $group->filePatterns === [] ? $defaultRegexes : array_map(Glob::toRegex(...), $group->filePatterns);
                $sources[] = [$group->directory, $regexes];
            }
        }

        $files = [];
        foreach ($sources as [$directory, $regexes]) {
            $path = $loaded->resolve($directory);
            if (!is_dir($path) && !is_file($path)) {
                throw new ConfigurationException($config->testDirectory === null && $config->testGroups === []
                    ? "There is no test directory yet: '$path' does not exist. Run 'microunit init' to create it with a first test, or point withTestDir() in your config at your tests."
                    : "The test directory '$path' does not exist. Check withTestDir()/withTestGroup() in your config.");
            }
            $files = [...$files, ...Filesystem::findFiles($path, $regexes)];
        }
        if ($files === []) {
            fwrite(STDERR, 'No test files found. Test files must match: ' . implode(', ', $config->testFilePatterns) . PHP_EOL);
        }

        return array_values(array_unique($files));
    }

    /** --list: one test id per line, or one JSON object per line with --list=json. */
    private function listTests(TestPlan $plan, string $format): void
    {
        foreach ($plan->entries as [, $test]) {
            echo $format === 'json'
                ? json_encode(['id' => $test->id, 'suite' => $test->suite, 'name' => $test->name, 'group' => $test->group, 'file' => $test->file, 'line' => $test->line], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                : $test->id;
            echo PHP_EOL;
        }
    }

    private function filterFor(CliOptions $options, LastRun $lastRun): TestFilter
    {
        try {
            return $this->selectedTests($options, $lastRun);
        } catch (\InvalidArgumentException $exception) {
            throw new UsageException($exception->getMessage());
        }
    }

    private function selectedTests(CliOptions $options, LastRun $lastRun): TestFilter
    {
        if ($options->idsFile !== null) {
            $ids = json_decode((string) @file_get_contents($options->idsFile), true);

            return TestFilter::exactIds(is_array($ids) ? array_values(array_filter($ids, is_string(...))) : []);
        }
        if (!$options->onlyFailed) {
            return TestFilter::fromPattern($options->filter);
        }

        $failedIds = $lastRun->failedTestIds();
        if ($failedIds === []) {
            fwrite(STDERR, 'No failed tests in the previous run: running everything.' . PHP_EOL);
            return TestFilter::fromPattern($options->filter);
        }

        return TestFilter::exactIds($failedIds);
    }

    /** `microunit integrity [REVISION]`: tests weakened compared to a git revision. */
    private function checkIntegrity(LoadedConfig $loaded, string $revision): int
    {
        $check = new IntegrityCheck($loaded->baseDirectory, $revision);
        if (!$check->isAvailable()) {
            throw new UsageException("The integrity check needs a git repository and the revision '$revision'.");
        }
        $style = TextStyle::forStream(STDOUT);
        $findings = $check->findings($this->testDirectories($loaded));
        foreach ($findings as $finding) {
            echo $style->yellow('!') . " {$finding['file']}:{$finding['line']} {$finding['test']}: {$finding['finding']}" . PHP_EOL;
        }
        echo ($findings === []
            ? $style->green("✔ No weakened tests compared to $revision.")
            : PHP_EOL . count($findings) . " finding(s) compared to $revision. Each one asks for a look; none of them proves a mistake.") . PHP_EOL;

        return $findings === [] ? self::EXIT_SUCCESS : self::EXIT_FAILURE;
    }

    /** @return list<string> the configured test directories, relative to the project */
    private function testDirectories(LoadedConfig $loaded): array
    {
        $config = $loaded->config;
        $directories = array_map(static fn(TestGroup $group): string => $group->directory, $config->testGroups);
        if ($config->testDirectory !== null || $directories === []) {
            $directories[] = $config->testDirectory ?? MicroUnitConfig::DEFAULT_TEST_DIRECTORY;
        }

        return array_values(array_unique($directories));
    }

    /**
     * The configured writers. In agent sessions the console writers make way for the agent's JSON
     * (file writers like JUnit keep working); configured AgentTestWriters get the integrity findings.
     *
     * @param (\Closure(): list<array{test: string, file: string, line: int, finding: string}>)|null $integrity
     * @param \Closure(): list<string> $checkFailures why the run fails beyond failed tests
     * @return list<ITestWriter>
     */
    private function writersFor(LoadedConfig $loaded, bool $isAgentSession, ?\Closure $integrity, \Closure $checkFailures): array
    {
        $writers = $loaded->config->testWriters;
        if ($isAgentSession) {
            $writers = array_values(array_filter($writers, static fn(ITestWriter $writer): bool => !$writer instanceof MinimalStringTestWriter && !$writer instanceof StringTestWriter));
            // A configured AgentTestWriter that writes to a file does not replace the agent's stdout.
            if (!array_any($writers, static fn(ITestWriter $writer): bool => $writer instanceof AgentTestWriter && $writer->writesToConsole())) {
                $writers[] = new AgentTestWriter(projectDirectory: $loaded->baseDirectory);
            }
        }
        $writers = array_map(static function (ITestWriter $writer) use ($integrity, $checkFailures, $loaded): ITestWriter {
            if (!$writer instanceof AgentTestWriter) {
                return $writer;
            }
            $writer = $writer->withRunFailures($checkFailures);

            return $integrity === null ? $writer : $writer->withIntegrityFindings($integrity, (string) $loaded->config->integrityCheck);
        }, $writers);
        if ($isAgentSession) {
            return $writers;
        }
        $hasGitHubWriter = array_any($writers, static fn(ITestWriter $writer): bool => $writer instanceof GitHubActionsTestWriter);
        if ($loaded->config->hasCiAnnotations && !$hasGitHubWriter && GitHubActionsTestWriter::isRunningOnGitHubActions()) {
            $writers[] = new GitHubActionsTestWriter();
        }

        return $writers;
    }

    /**
     * Worker processes for this run. Suites are the unit of distribution, so there are never more
     * workers than suites. In auto mode (0) a run estimated to take under a second (the last run
     * time per test, times the tests selected now) stays sequential: starting the workers would
     * cost more than it saves.
     */
    private function workerCount(CliOptions $options, LoadedConfig $loaded, TestPlan $plan, LastRun $lastRun): int
    {
        $requested = $options->parallelWorkers ?? $loaded->config->parallelWorkers;
        $suites = count($plan->bySuite());
        if ($requested === 1 || $suites < 2) {
            return 1;
        }
        if ($requested === 0 && ($lastRun->estimatedSeconds($plan->count()) ?? PHP_INT_MAX) < self::PARALLEL_WORTH_SECONDS) {
            return 1;
        }
        if (!PhpProcess::canSpawn()) {
            fwrite(STDERR, 'proc_open() is disabled on this system; running tests sequentially.' . PHP_EOL);
            return 1;
        }

        return min($requested === 0 ? PhpProcess::cpuCount() : $requested, $suites);
    }

    /** @return \Closure(int $index, int $count, string $outputFile): list<string> */
    private function workerCommand(LoadedConfig $loaded, CliOptions $options, bool $withCoverage, RuntimeDirectory $runtime): \Closure
    {
        $base = [...$this->forwardedOptions($loaded, $options), $withCoverage ? '--coverage' : '--no-coverage'];
        // Workers reuse compiled bytecode across runs: unless coverage needs to see every include.
        $phpFlags = $withCoverage ? [] : ['-d', 'opcache.enable_cli=1', '-d', 'opcache.file_cache=' . $runtime->opcache(), '-d', 'opcache.file_cache_only=1'];

        return fn(int $index, int $count, string $outputFile): array => [
            ...PhpProcess::command($this->entryScript, $phpFlags),
            ...$base,
            "--worker=$index/$count",
            '--worker-output=' . $outputFile,
        ];
    }

    /** @return list<string> */
    private function runnerCommand(LoadedConfig $loaded): array
    {
        return [...PhpProcess::command($this->entryScript), ...($loaded->file === null ? [] : ['--config=' . $loaded->file])];
    }

    /** @return list<string> options a subprocess needs to see the same tests */
    private function forwardedOptions(LoadedConfig $loaded, CliOptions $options): array
    {
        return array_values(array_filter([
            $loaded->file === null ? null : '--config=' . $loaded->file,
            $options->filter === null ? null : '--filter=' . $options->filter,
            $options->groups === [] ? null : '--group=' . implode(',', $options->groups),
            $options->onlyFailed ? '--failed' : null,
            $options->stopOnFailure ? '--stop-on-failure' : null,
            $options->idsFile === null ? null : '--ids-file=' . $options->idsFile,
            ...$options->paths,
        ], static fn(?string $option): bool => $option !== null));
    }

    /**
     * The explorer is opt-in: its report is only built when it is wanted for this run, when the
     * explorer itself started the run, or when it is open and should show the results. Otherwise a
     * run costs nothing for it.
     *
     * @param array<string, mixed>|null $coverageSummary
     */
    private function publishToExplorer(LoadedConfig $loaded, CliOptions $options, TestPlan $plan, RunSummary $summary, RuntimeDirectory $runtime, ?array $coverageSummary): void
    {
        $isWanted = ($options->explorer ?? $loaded->config->isTestExplorerEnabled) && !$options->isExplorerTriggered;
        $explorerState = $this->explorerState($loaded, $runtime);
        if (!$isWanted && !$options->isExplorerTriggered && $explorerState->liveServer() === null) {
            return;
        }

        $report = ExplorerReport::build(
            TestRegistry::instance()->testers(),
            $summary,
            $explorerState->readReport(),
            $plan->count() < array_sum(array_map(static fn($tester) => count($tester->getTests()), TestRegistry::instance()->testers())),
            $coverageSummary,
            $loaded->config->editor,
            $loaded->config->framework?->name(),
        );
        $explorerState->writeReport($report);

        if ($isWanted && ExplorerLauncher::isInteractiveSession()) {
            $this->explorerLauncher($loaded, $explorerState)->publish();
        }
    }

    private function explorerState(LoadedConfig $loaded, RuntimeDirectory $runtime): ExplorerState
    {
        return new ExplorerState($runtime->explorer(), $loaded->baseDirectory);
    }

    private function explorerLauncher(LoadedConfig $loaded, ExplorerState $explorerState): ExplorerLauncher
    {
        return new ExplorerLauncher($explorerState, $loaded->config->testExplorerPort, [...$this->runnerCommand($loaded), '--explorer-server']);
    }

    private function applyColorPreference(?bool $colors): void
    {
        if ($colors === null) {
            return;
        }
        // A flag wins over the environment, so it also clears the opposite variable.
        putenv($colors ? 'FORCE_COLOR=1' : 'NO_COLOR=1');
        putenv($colors ? 'NO_COLOR' : 'FORCE_COLOR');
    }

    private function printHelp(?string $command): int
    {
        echo HelpText::render(TextStyle::forStream(STDOUT), $command);
        return self::EXIT_SUCCESS;
    }

    private function printVersion(): int
    {
        echo 'microunit ', MicroUnit::VERSION, ' (PHP ', PHP_VERSION, ')', PHP_EOL;
        return self::EXIT_SUCCESS;
    }
}
