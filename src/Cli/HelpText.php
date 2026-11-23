<?php

namespace MicroUnit\Cli;

use MicroUnit\MicroUnit;
use MicroUnit\Output\Format\TextStyle;

/**
 * `microunit help` and `microunit help <command>`. The texts are data: every command lists its
 * options in groups, with examples; the docs' CLI page mirrors them.
 */
final class HelpText
{
    private const string DOCS = 'https://mitarnik04.github.io/MicroUnit/';

    /** command => [summary, usage, description, [group => [[option, explanation], ...]], examples, docs page] */
    private const array COMMANDS = [
        'run' => [
            'Run the tests (the default command)',
            'microunit [options] [paths...]',
            'Runs the tests the config selects, or the test files and directories given as paths.',
            [
                'Select tests' => [
                    ['[paths...]', 'Test files or directories instead of the configured ones'],
                    ['--filter=PATTERN', 'Tests whose "Suite::test" id contains PATTERN, or matches /regex/'],
                    ['--group=NAME[,NAME]', 'Only these test groups (withTestGroup())'],
                    ['--failed', 'Only the tests that failed in the previous run'],
                    ['--list[=json]', 'List the selected tests instead of running them'],
                ],
                'Run' => [
                    ['--parallel[=N]', 'N worker processes (default: one per CPU core)'],
                    ['--no-parallel', 'Run sequentially'],
                    ['--stop-on-failure', 'Stop after the first failing test'],
                    ['--update-snapshots', 'Rewrite the snapshots of the tests that run'],
                    ['--config=FILE, -c', 'Use this config file instead of finding microunit.config.php'],
                ],
                'Output' => [
                    ['--agent, --no-agent', 'Compact JSON for AI agents (automatic in agent sessions)'],
                    ['--colors, --no-colors', 'Force coloured output on or off'],
                    ['--coverage, --no-coverage', 'Collect code coverage for this run, or skip it'],
                    ['--explorer, --no-explorer', 'Open the Test Explorer after this run, or not even when configured'],
                ],
            ],
            [
                'microunit',
                'microunit tests/Unit --filter=Cart',
                'microunit --failed --stop-on-failure',
                'microunit --parallel --coverage',
            ],
            'writing-tests',
        ],
        'init' => [
            'Create microunit.config.php and a first test',
            'microunit init',
            'Writes microunit.config.php and tests/example-tests.php, set up for Laravel, Symfony or CodeIgniter when composer.json uses one. Never overwrites existing files (--skill refreshes the skill to the installed version).',
            [
                'Options' => [
                    ['--skill', 'Also install the skill that teaches AI coding agents MicroUnit (.claude/skills/microunit)'],
                ],
            ],
            ['microunit init', 'microunit init --skill'],
            'getting-started',
        ],
        'migrate' => [
            'Convert PHPUnit tests to MicroUnit',
            'microunit migrate [paths...] [--out=DIR]',
            'Translates PHPUnit test classes (default: tests/) into MicroUnit test files in tests-microunit/. The originals stay untouched, so both suites can run side by side. Properties become fixtures, helpers become functions, data providers become data sets, PHPUnit mocks become MicroUnit mocks. What needs manual work is written as a skipped test with the reason and the original code, and listed in migration-report.json. Creates microunit.config.php from phpunit.xml when there is none.',
            [
                'Options' => [
                    ['[paths...]', 'PHPUnit test files or directories (default: tests)'],
                    ['--out=DIR', 'Where the MicroUnit tests go (default: tests-microunit)'],
                ],
            ],
            ['microunit migrate', 'microunit migrate tests/Unit --out=tests-new'],
            'migrating-from-phpunit',
        ],
        'integrity' => [
            'Find tests that were weakened since a git revision',
            'microunit integrity [revision]',
            'Compares the tests with a git revision (default: HEAD) and lists removed tests, removed assertions, new skips, changed expected values, removed data sets and assertions that cannot fail. Meant for reviewing changes, especially by AI agents. Exit code 1 when something was found. To run it after every test run as well, configure withIntegrityCheck().',
            [],
            ['microunit integrity', 'microunit integrity origin/main'],
            'ai-agents',
        ],
        'explorer' => [
            'Open the Test Explorer',
            'microunit explorer',
            'Opens the Test Explorer in the browser, where you run tests and read the results. It is opt-in: to open it after every interactive run, configure withTestExplorer() (which also sets its port and editor); for a single run, pass --explorer.',
            [],
            ['microunit explorer'],
            'test-explorer',
        ],
    ];

    private function __construct() {}

    public static function render(TextStyle $style, ?string $command = null): string
    {
        if ($command !== null && $command !== 'help') {
            $definition = self::COMMANDS[$command] ?? throw new UsageException("There is no command '$command'. Commands: " . implode(', ', array_keys(self::COMMANDS)) . '.');

            return self::command($style, $command, $definition);
        }

        $lines = [
            $style->bold('microunit') . ' ' . $style->dim(MicroUnit::VERSION) . ': the dependency-free PHP testing framework',
            '',
            $style->bold('Usage'),
            '  microunit [command] [options] [paths...]',
            '',
            $style->bold('Commands'),
        ];
        foreach (self::COMMANDS as $name => [$summary]) {
            $lines[] = '  ' . str_pad($name === 'run' ? '(none)' : $name, 12) . $summary;
        }
        foreach (self::COMMANDS['run'][3] as $group => $options) {
            $lines = [...$lines, '', $style->bold($group . ($group === 'Select tests' ? ' (when running)' : '')), ...self::options($options)];
        }
        $lines = [
            ...$lines,
            '',
            $style->bold('Other'),
            ...self::options([['--help, -h', 'This help; `microunit help <command>` for details'], ['--version, -V', 'The version']]),
            '',
            $style->bold('Exit codes'),
            '  0  all tests passed',
            '  1  tests failed, coverage below the minimum, or a configured strict check failed',
            '  2  usage or configuration error',
            '',
            'Every option can also be set in microunit.config.php; flags only change one run.',
            'Docs: ' . self::DOCS,
        ];

        return implode(PHP_EOL, $lines) . PHP_EOL;
    }

    /** @param array{string, string, string, array<string, list<array{string, string}>>, list<string>, string} $definition */
    private static function command(TextStyle $style, string $name, array $definition): string
    {
        [$summary, $usage, $description, $groups, $examples, $page] = $definition;
        $lines = [
            $style->bold("microunit $name") . ': ' . $summary,
            '',
            $style->bold('Usage'),
            "  $usage",
            '',
            ...array_map(static fn(string $line): string => "  $line", explode("\n", wordwrap($description, 96))),
        ];
        foreach ($groups as $group => $options) {
            $lines = [...$lines, '', $style->bold($group), ...self::options($options)];
        }
        $lines = [...$lines, '', $style->bold('Examples'), ...array_map(static fn(string $example): string => "  $example", $examples), '', 'Docs: ' . self::DOCS . $page];

        return implode(PHP_EOL, $lines) . PHP_EOL;
    }

    /**
     * @param list<array{string, string}> $options
     * @return list<string>
     */
    private static function options(array $options): array
    {
        return array_map(static fn(array $option): string => '  ' . str_pad($option[0], 27) . $option[1], $options);
    }
}
