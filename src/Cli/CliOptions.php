<?php

namespace MicroUnit\Cli;

/** Parsed command line. Every option only overrides microunit.config.php for this one run. */
final readonly class CliOptions
{
    /**
     * @param list<string> $paths test files or directories to run instead of the configured test directory
     * @param bool|null $coverage true = --coverage, false = --no-coverage, null = as configured
     * @param int|null $parallelWorkers 0 = one per core, 1 = sequential, null = as configured
     * @param bool|null $explorer true = --explorer, false = --no-explorer, null = as configured
     * @param list<string> $groups test groups to run (--group=a,b); empty = all
     * @param bool $onlyFailed re-run only the tests that failed in the previous run (--failed)
     * @param string|null $list 'text' or 'json' to list the selected tests instead of running them (--list[=json])
     * @param string|null $idsFile JSON list of exact test ids to run (internal, used by the Test Explorer)
     */
    public function __construct(
        public string $command = 'run',
        public array $paths = [],
        public ?string $filter = null,
        public ?string $configFile = null,
        public ?bool $coverage = null,
        public ?int $parallelWorkers = null,
        public ?bool $explorer = null,
        public bool $stopOnFailure = false,
        public ?bool $colors = null,
        public ?WorkerAssignment $worker = null,
        public bool $isExplorerTriggered = false,
        public ?int $explorerPort = null,
        public ?string $explorerProject = null,
        public array $groups = [],
        public bool $onlyFailed = false,
        public ?string $idsFile = null,
        public bool $updateSnapshots = false,
        public ?string $outputDirectory = null,
        public ?string $list = null,
        public ?bool $agent = null,
        public ?string $helpTopic = null,
        public bool $installAgentSkill = false,
    ) {}

    /** Options whose value is required (optional values like --parallel[=N] only take "="). */
    private const array VALUE_OPTIONS = ['--filter', '--config', '-c', '--group', '--out', '--port', '--project', '--worker', '--worker-output', '--ids-file'];

    /** @param list<string> $arguments argv without the script name */
    public static function parse(array $arguments): self
    {
        $values = [];
        $paths = [];
        $worker = ['index' => null, 'count' => null, 'output' => null];

        if (in_array($arguments[0] ?? '', ['init', 'migrate', 'integrity', 'explorer', 'help', 'version'], true)) {
            $values['command'] = array_shift($arguments);
        }

        for ($index = 0, $count = count($arguments); $index < $count; $index++) {
            $argument = $arguments[$index];
            if (!str_starts_with($argument, '-')) {
                $paths[] = $argument;
                continue;
            }

            [$name, $value] = str_contains($argument, '=') ? explode('=', $argument, 2) : [$argument, null];
            // Options that need a value also take it as the next argument: -c FILE, --filter Cart.
            if ($value === null && in_array($name, self::VALUE_OPTIONS, true) && isset($arguments[$index + 1])) {
                $value = $arguments[++$index];
            }
            match ($name) {
                '-h', '--help' => [$values['helpTopic'], $values['command']] = [($values['command'] ?? 'run') === 'run' ? null : $values['command'], 'help'],
                '-V', '--version' => $values['command'] = 'version',
                '--explorer' => $values['explorer'] = true,
                '--filter' => $values['filter'] = self::requireValue($name, $value),
                '--config', '-c' => $values['configFile'] = self::requireValue($name, $value),
                '--coverage' => $values['coverage'] = true,
                '--no-coverage' => $values['coverage'] = false,
                '--parallel' => $values['parallelWorkers'] = $value === null ? 0 : self::positiveInt($name, $value),
                '--no-parallel' => $values['parallelWorkers'] = 1,
                '--no-explorer' => $values['explorer'] = false,
                '--stop-on-failure' => $values['stopOnFailure'] = true,
                '--group' => $values['groups'] = array_values(array_filter(array_map('trim', explode(',', self::requireValue($name, $value))))),
                '--failed' => $values['onlyFailed'] = true,
                '--update-snapshots' => $values['updateSnapshots'] = true,
                '--skill' => $values['installAgentSkill'] = true,
                '--agent' => $values['agent'] = true,
                '--no-agent' => $values['agent'] = false,
                '--list' => $values['list'] = in_array($value, [null, 'text', 'json'], true) ? ($value ?? 'text') : throw new UsageException("--list takes no value or =json, got '$value'."),
                '--out' => $values['outputDirectory'] = self::requireValue($name, $value),
                '--colors' => $values['colors'] = true,
                '--no-colors' => $values['colors'] = false,
                // Internal options used by MicroUnit's own subprocesses.
                '--worker' => [$worker['index'], $worker['count']] = self::workerSlot(self::requireValue($name, $value)),
                '--worker-output' => $worker['output'] = self::requireValue($name, $value),
                '--explorer-run' => $values['isExplorerTriggered'] = true,
                '--explorer-server' => $values['command'] = 'explorer-server',
                '--port' => $values['explorerPort'] = self::positiveInt($name, self::requireValue($name, $value)),
                '--project' => $values['explorerProject'] = self::requireValue($name, $value),
                '--ids-file' => $values['idsFile'] = self::requireValue($name, $value),
                default => throw new UsageException("Unknown option '$name'. Run 'microunit --help' for the list of options."),
            };
        }

        if ($worker['index'] !== null) {
            $values['worker'] = new WorkerAssignment($worker['index'], $worker['count'], $worker['output'] ?? throw new UsageException('--worker requires --worker-output.'));
        }

        return new self(...$values, paths: $paths);
    }

    private static function requireValue(string $name, ?string $value): string
    {
        if ($value === null || $value === '') {
            throw new UsageException("Option '$name' needs a value, e.g. $name=...");
        }

        return $value;
    }

    private static function positiveInt(string $name, string $value): int
    {
        if (!ctype_digit($value) || (int) $value < 1) {
            throw new UsageException("Option '$name' expects a positive number, got '$value'.");
        }

        return (int) $value;
    }

    /** @return array{int, int} */
    private static function workerSlot(string $value): array
    {
        if (preg_match('~^(\d+)/(\d+)$~', $value, $match) !== 1 || (int) $match[1] >= (int) $match[2]) {
            throw new UsageException("Invalid --worker value '$value'.");
        }

        return [(int) $match[1], (int) $match[2]];
    }
}
