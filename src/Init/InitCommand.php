<?php

namespace MicroUnit\Init;

use MicroUnit\Config\MicroUnitConfig;
use MicroUnit\Helpers\Filesystem;
use MicroUnit\MicroUnit;
use MicroUnit\Migrate\PhpUnitConfiguration;
use MicroUnit\Output\Format\TextStyle;

/**
 * `microunit init`: writes microunit.config.php and a first test, set up for the framework the
 * project uses (detected from composer.json). Never overwrites an existing config or test;
 * `--skill` installs (or updates) the agent skill file.
 */
final readonly class InitCommand
{
    private const string EXAMPLE_TEST = 'tests/example-tests.php';

    /** composer package => [integration class, label] */
    private const array FRAMEWORKS = [
        'laravel/framework' => ['MicroUnit\Integration\Laravel\LaravelIntegration', 'Laravel'],
        'symfony/framework-bundle' => ['MicroUnit\Integration\Symfony\SymfonyIntegration', 'Symfony'],
        'codeigniter4/framework' => ['MicroUnit\Integration\CodeIgniter\CodeIgniterIntegration', 'CodeIgniter'],
    ];

    private const string SKILL_TARGET = '.claude/skills/microunit/SKILL.md';

    /** @param bool $withAgentSkill also install the skill that teaches AI coding agents MicroUnit (`init --skill`) */
    public function __construct(
        private string $projectDirectory,
        private bool $withAgentSkill = false,
    ) {}

    public function run(): int
    {
        $style = TextStyle::forStream(STDOUT);
        $configFile = $this->projectDirectory . DIRECTORY_SEPARATOR . MicroUnit::CONFIG_FILE_NAME;
        if ($this->withAgentSkill) {
            $this->installAgentSkill($style);
            if (is_file($configFile)) {
                return 0;
            }
        }
        if (is_file($configFile)) {
            fwrite(STDERR, MicroUnit::CONFIG_FILE_NAME . ' already exists; nothing was changed.' . PHP_EOL);

            return 2;
        }

        $framework = $this->detectFramework();
        Filesystem::writeAtomically($configFile, $this->config($framework));
        echo $style->green('✔') . ' Created ' . MicroUnit::CONFIG_FILE_NAME . ($framework === null ? '' : " (set up for {$framework[1]})") . PHP_EOL;

        $exampleFile = $this->projectDirectory . DIRECTORY_SEPARATOR . self::EXAMPLE_TEST;
        if (!is_file($exampleFile)) {
            Filesystem::writeAtomically($exampleFile, $this->exampleTest($framework));
            echo $style->green('✔') . ' Created ' . self::EXAMPLE_TEST . PHP_EOL;
        }

        echo PHP_EOL . 'Run your tests with ' . $style->bold('vendor/bin/microunit') . PHP_EOL;
        if (!$this->withAgentSkill) {
            echo $style->dim('Working with an AI coding agent? `microunit init --skill` teaches it MicroUnit.') . PHP_EOL;
        }

        return 0;
    }

    /**
     * Copies the MicroUnit skill for AI coding agents into the project (Claude Code reads
     * .claude/skills/) and prints the lines other agents need in AGENTS.md.
     */
    private function installAgentSkill(TextStyle $style): void
    {
        $target = $this->projectDirectory . DIRECTORY_SEPARATOR . self::SKILL_TARGET;
        Filesystem::writeAtomically($target, (string) file_get_contents(dirname(__DIR__, 2) . '/resources/agent/SKILL.md'));
        echo $style->green('✔') . ' Installed the MicroUnit skill for AI agents: ' . self::SKILL_TARGET . PHP_EOL
            . '  Other agents: add to AGENTS.md (or your agent\'s instructions file):' . PHP_EOL
            . $style->dim('  ## Tests' . PHP_EOL . '  Tests use MicroUnit. Read ' . self::SKILL_TARGET . ' before writing or running tests.') . PHP_EOL;
    }

    /** @return array{string, string}|null [integration class, label] */
    private function detectFramework(): ?array
    {
        $composer = json_decode((string) @file_get_contents($this->projectDirectory . '/composer.json'), true);
        $packages = [...array_keys($composer['require'] ?? []), ...array_keys($composer['require-dev'] ?? [])];

        foreach (self::FRAMEWORKS as $package => $framework) {
            if (in_array($package, $packages, true)) {
                return $framework;
            }
        }

        return null;
    }

    /** @param array{string, string}|null $framework */
    private function config(?array $framework): string
    {
        $imports = ['MicroUnit\Config\MicroUnitConfigBuilder'];
        $frameworkLine = '';
        if ($framework !== null) {
            $imports[] = $framework[0];
            $environment = PhpUnitConfiguration::read($this->projectDirectory)->environmentArgument();
            $frameworkLine = PHP_EOL . '    ->withFramework(new ' . substr(strrchr($framework[0], '\\'), 1) . "($environment))";
            // Symfony loads .env.test and KERNEL_CLASS in its test bootstrap.
            if ($framework[1] === 'Symfony' && is_file($this->projectDirectory . '/tests/bootstrap.php')) {
                $frameworkLine = PHP_EOL . "    ->withBootstrapFile('tests/bootstrap.php')" . $frameworkLine;
            }
        }
        sort($imports);
        $uses = implode(PHP_EOL, array_map(static fn(string $class): string => "use $class;", $imports));
        $directory = MicroUnitConfig::DEFAULT_TEST_DIRECTORY;

        return <<<PHP
            <?php

            $uses

            // Every option: https://mitarnik04.github.io/MicroUnit/configuration
            return MicroUnitConfigBuilder::create()
                ->withTestDir('$directory'){$frameworkLine}
                ->build();

            PHP;
    }

    /** @param array{string, string}|null $framework */
    private function exampleTest(?array $framework): string
    {
        return match ($framework[1] ?? null) {
            'Laravel' => <<<'PHP'
                <?php

                use MicroUnit\Integration\Laravel\Laravel;
                use MicroUnit\Setup\TestSetup;

                $tester = TestSetup::getTester('Home');

                $tester->define('the_home_page_loads', function (): void {
                    Laravel::get('/')->assertOk();
                });

                PHP,
            'Symfony' => <<<'PHP'
                <?php

                use MicroUnit\Assertion\Assert;
                use MicroUnit\Integration\Symfony\Symfony;
                use MicroUnit\Setup\TestSetup;

                $tester = TestSetup::getTester('Kernel');

                $tester->define('the_container_has_a_router', function (): void {
                    Assert::isTrue(Symfony::container()->has('router'));
                });

                PHP,
            default => <<<'PHP'
                <?php

                use MicroUnit\Assertion\Assert;
                use MicroUnit\Setup\TestSetup;

                $tester = TestSetup::getTester('Example');

                $tester->define('adds_two_numbers', function (): void {
                    Assert::exact(4, 2 + 2);
                });

                $tester->define('refuses_to_divide_by_zero', function (): void {
                    Assert::throws(fn() => intdiv(1, 0), DivisionByZeroError::class);
                });

                PHP,
        };
    }
}
