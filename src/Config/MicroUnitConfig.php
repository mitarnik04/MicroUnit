<?php

namespace MicroUnit\Config;

use MicroUnit\Integration\IFrameworkIntegration;
use MicroUnit\Output\ITestWriter;
use MicroUnit\Output\MinimalStringTestWriter;

/** The complete, immutable configuration of a run. Usually created through MicroUnitConfigBuilder. */
final readonly class MicroUnitConfig
{
    public const int DEFAULT_TEST_EXPLORER_PORT = 7357;

    /** Searched for tests when neither withTestDir() nor withTestGroup() is configured. */
    public const string DEFAULT_TEST_DIRECTORY = 'tests';

    /**
     * @param string|null $testDirectory relative to the config file, or absolute; null = DEFAULT_TEST_DIRECTORY, unless test groups are configured
     * @param list<ITestWriter> $testWriters
     * @param list<string> $testFilePatterns file name globs, e.g. "*Test.php"
     * @param string|null $bootstrapFile relative to the config file, or absolute
     * @param int $parallelWorkers 1 = sequential, 0 = one worker per CPU core
     * @param string $editor editor name (vscode, phpstorm, ...) or a URL template with {file} and {line}
     * @param list<TestGroup> $testGroups named test sets in addition to (or instead of) $testDirectory
     * @param string $runtimeDirectory caches, run logs and explorer state; relative to the config file
     * @param bool|null $agentOutput compact JSON output for AI agents: true always, false never, null when an agent session is detected
     * @param string|null $integrityCheck git revision to find weakened tests against after every run; null = off
     * @param bool $failOnWeakenedTests a run with integrity findings fails
     * @param bool $failOnRiskyTests a run with tests that passed without an assertion fails
     */
    public function __construct(
        public ?string $testDirectory = null,
        public array $testWriters = [new MinimalStringTestWriter()],
        public array $testFilePatterns = ['*-tests.php'],
        public bool $stopOnFailure = false,
        public ?string $bootstrapFile = null,
        public bool $persistRunLogs = false,
        public ?CoverageConfig $coverage = null,
        public bool $isTestExplorerEnabled = false,
        public int $testExplorerPort = self::DEFAULT_TEST_EXPLORER_PORT,
        public int $parallelWorkers = 1,
        public ?IFrameworkIntegration $framework = null,
        public bool $hasCiAnnotations = true,
        public string $editor = 'vscode',
        public array $testGroups = [],
        public string $runtimeDirectory = '.microunit',
        public ?bool $agentOutput = null,
        public ?string $integrityCheck = null,
        public bool $failOnWeakenedTests = false,
        public bool $failOnRiskyTests = false,
    ) {}
}
