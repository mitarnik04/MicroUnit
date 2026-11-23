<?php

namespace MicroUnit\Config;

use MicroUnit\Integration\IFrameworkIntegration;
use MicroUnit\Output\ITestWriter;

/**
 * Fluent builder for microunit.config.php. Every setting is optional; each method says what
 * applies when you leave it out, and every optional parameter names its default.
 *
 *     return MicroUnitConfigBuilder::create()
 *         ->withTestDir('tests')
 *         ->addTestFilePattern('*Test.php')
 *         ->build();
 *
 * @phpstan-type Settings array{testDirectory?: string, testWriters?: list<ITestWriter>, testFilePatterns?: list<string>,
 *     stopOnFailure?: bool, bootstrapFile?: string, persistRunLogs?: bool, coverage?: CoverageConfig,
 *     isTestExplorerEnabled?: bool, testExplorerPort?: int, parallelWorkers?: int, framework?: IFrameworkIntegration,
 *     hasCiAnnotations?: bool, editor?: string, testGroups?: list<TestGroup>, runtimeDirectory?: string,
 *     agentOutput?: bool, integrityCheck?: string, failOnWeakenedTests?: bool, failOnRiskyTests?: bool}
 */
final class MicroUnitConfigBuilder
{
    /** @var Settings named arguments for MicroUnitConfig; anything unset keeps its default */
    private array $settings = [];

    private function __construct() {}

    public static function create(): self
    {
        return new self();
    }

    /**
     * Directory searched recursively for test files, relative to the config file (absolute works too).
     * Not set: "tests" next to the config file (`microunit init` creates it); the run stops with a hint when it is missing.
     */
    public function withTestDir(string $testDirectory): self
    {
        return $this->with('testDirectory', $testDirectory);
    }

    /** Replaces the configured writers. Not set: the console output (MinimalStringTestWriter). */
    public function withTestWriters(ITestWriter ...$writers): self
    {
        return $this->with('testWriters', array_values($writers));
    }

    /** Adds a writer to the configured ones (by default the console output); withTestWriters() replaces them. */
    public function addTestWriter(ITestWriter $writer): self
    {
        return $this->with('testWriters', [...$this->settings['testWriters'] ?? new MicroUnitConfig()->testWriters, $writer]);
    }

    /** Replaces the test file name globs. Not set: "*-tests.php". */
    public function withTestFilePatterns(string ...$patterns): self
    {
        return $this->with('testFilePatterns', array_values($patterns));
    }

    /** Adds a glob to the configured ones (by default "*-tests.php"); withTestFilePatterns() replaces them. */
    public function addTestFilePattern(string $pattern): self
    {
        return $this->with('testFilePatterns', [...$this->settings['testFilePatterns'] ?? new MicroUnitConfig()->testFilePatterns, $pattern]);
    }

    /** Stops the run at the first failing test (per worker in parallel runs). Not set: every test runs. */
    public function stopOnFailure(): self
    {
        return $this->with('stopOnFailure', true);
    }

    /** PHP file included before any test file, relative to the config file (absolute works too). Not set: none. */
    public function withBootstrapFile(string $bootstrapFile): self
    {
        return $this->with('bootstrapFile', $bootstrapFile);
    }

    /** Keeps the run log of every run. Not set: only the latest run's log is kept. */
    public function persistRunLogs(): self
    {
        return $this->with('persistRunLogs', true);
    }

    /**
     * Measures code coverage on every run (skip it for one run with --no-coverage). Not set: no
     * coverage, unless a run passes --coverage, which then uses the defaults below.
     *
     *     ->withCoverage(include: 'src', html: 'build/coverage', minimum: 80)
     *
     * @param string|array<string> $include directories or files to measure. Default: "src"
     * @param string|array<string> $exclude directories or files inside $include to skip. Default: none
     * @param string|null $html directory for the HTML report. Default: no HTML report
     * @param string|null $clover file for the Clover XML report. Default: no Clover report
     * @param string|null $cobertura file for the Cobertura XML report. Default: no Cobertura report
     * @param bool $text print a coverage table to the console. Default: true
     * @param float|null $minimum fail the run when line coverage is below this percentage. Default: no minimum
     */
    public function withCoverage(
        string|array $include = 'src',
        string|array $exclude = [],
        ?string $html = null,
        ?string $clover = null,
        ?string $cobertura = null,
        bool $text = true,
        ?float $minimum = null,
    ): self {
        return $this->with('coverage', new CoverageConfig(
            array_values((array) $include),
            array_values((array) $exclude),
            $html,
            $clover,
            $cobertura,
            $text,
            $minimum,
        ));
    }

    /**
     * The Test Explorer. Not set: it never opens by itself; `microunit explorer` opens it on demand
     * and `--explorer` after a single run, both with the defaults below. It never opens in CI or
     * when the output is piped.
     *
     *     ->withTestExplorer()                                        // open it after every run
     *     ->withTestExplorer(openAfterRun: false, editor: 'phpstorm') // on demand only, PhpStorm links
     *
     * @param bool $openAfterRun open the explorer (or update the open one) after every interactive run. Default: true
     * @param int $port the local port it listens on. Default: 7357
     * @param string $editor what its file links open: vscode, cursor, windsurf, phpstorm, idea, sublime,
     *                       zed, or a URL template such as "myeditor://open?file={file}&line={line}".
     *                       Default: "vscode"
     */
    public function withTestExplorer(bool $openAfterRun = true, int $port = MicroUnitConfig::DEFAULT_TEST_EXPLORER_PORT, string $editor = 'vscode'): self
    {
        return $this->with('isTestExplorerEnabled', $openAfterRun)->with('testExplorerPort', $port)->with('editor', $editor);
    }

    /**
     * Runs the tests in worker processes on every run (one suite stays in one worker). Not set:
     * sequential, unless a run passes --parallel.
     *
     * @param int|null $workers number of worker processes. Default: one per CPU core
     */
    public function runInParallel(?int $workers = null): self
    {
        return $this->with('parallelWorkers', $workers ?? 0);
    }

    /** Boots a framework (Laravel, Symfony, CodeIgniter, ...) around every test. Not set: plain PHP. */
    public function withFramework(IFrameworkIntegration $framework): self
    {
        return $this->with('framework', $framework);
    }

    /**
     * Adds a named group of tests, e.g. withTestGroup('integration', 'tests/Integration').
     * Run one group with --group=integration; without --group all groups run. Not set: no groups,
     * the test directory is the only source of tests.
     *
     * @param string ...$filePatterns file name globs. Default: the configured test file patterns
     */
    public function withTestGroup(string $name, string $directory, string ...$filePatterns): self
    {
        return $this->with('testGroups', [
            ...$this->settings['testGroups'] ?? [],
            new TestGroup($name, $directory, array_values($filePatterns)),
        ]);
    }

    /** Where caches, run logs and explorer state live. Not set: .microunit next to the config file. */
    public function withRuntimeDirectory(string $directory): self
    {
        return $this->with('runtimeDirectory', $directory);
    }

    /** Disables the GitHub Actions annotations. Not set: added automatically on GitHub Actions. */
    public function withoutCiAnnotations(): self
    {
        return $this->with('hasCiAnnotations', false);
    }

    /**
     * Output for AI coding agents: one compact JSON object with the failures only, instead of the
     * console writers. Not set: it switches on by itself when an agent runs the tests (Claude Code,
     * or any agent that sets MICROUNIT_AGENT=1).
     *
     * @param bool $enabled true forces it on in every run, false turns it off even for agents. Default: true
     */
    public function withAgentOutput(bool $enabled = true): self
    {
        return $this->with('agentOutput', $enabled);
    }

    /**
     * After every run, lists tests that were weakened compared to a git revision: removed tests,
     * removed assertions, new skips, changed expected values (`microunit integrity` does the same on
     * demand). Not set: the check never runs; it is opt-in.
     *
     *     ->withIntegrityCheck('origin/main', failOnFindings: true) // e.g. in CI for pull requests
     *
     * @param string $revision the git revision to compare with. Default: "HEAD" (your uncommitted changes)
     * @param bool $failOnFindings fail the run on findings, or when the comparison cannot run. Default: false
     */
    public function withIntegrityCheck(string $revision = 'HEAD', bool $failOnFindings = false): self
    {
        return $this->with('integrityCheck', $revision)->with('failOnWeakenedTests', $failOnFindings);
    }

    /** Fails the run when a test passes without a single assertion (it proves nothing). Not set: only a warning. */
    public function failOnRiskyTests(): self
    {
        return $this->with('failOnRiskyTests', true);
    }

    public function build(): MicroUnitConfig
    {
        return new MicroUnitConfig(...$this->settings);
    }

    /**
     * @template TSetting of key-of<Settings>
     * @param TSetting $setting
     * @param Settings[TSetting] $value
     */
    private function with(string $setting, mixed $value): self
    {
        // @phpstan-ignore assign.propertyType (PHPStan cannot tie $value to the type of the $setting key)
        $this->settings[$setting] = $value;
        return $this;
    }
}
