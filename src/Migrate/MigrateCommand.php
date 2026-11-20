<?php

namespace MicroUnit\Migrate;

use MicroUnit\Helpers\Filesystem;
use MicroUnit\Output\Format\TextStyle;

/**
 * `microunit migrate [paths] [--out=DIR]`: converts PHPUnit test classes to MicroUnit test files.
 * The originals stay untouched; the converted suite goes to its own directory so both can run side
 * by side until you switch over. Tests that need manual work are written as skipped tests (with the
 * reason and the original code), listed in the summary and in migration-report.json.
 */
final readonly class MigrateCommand
{
    public const string DEFAULT_OUTPUT = 'tests-microunit';

    private const array INTEGRATIONS = [
        'Laravel' => 'MicroUnit\Integration\Laravel\LaravelIntegration',
        'Symfony' => 'MicroUnit\Integration\Symfony\SymfonyIntegration',
        'CodeIgniter' => 'MicroUnit\Integration\CodeIgniter\CodeIgniterIntegration',
    ];

    /** @param list<string> $paths files or directories with PHPUnit tests (default: tests) */
    public function __construct(
        private string $projectDirectory,
        private array $paths,
        private string $outputDirectory,
    ) {}

    public function run(): int
    {
        $style = TextStyle::forStream(STDOUT);
        $paths = $this->paths === [] ? ['tests'] : $this->paths;
        $output = Filesystem::resolve($this->projectDirectory, $this->outputDirectory);

        [$files, $classes, $declarations] = $this->parse($paths, $output);
        if ($files === []) {
            fwrite(STDERR, 'No PHP files found in ' . implode(', ', $paths) . '.' . PHP_EOL);

            return 2;
        }

        [$tests, $migratedClasses] = $this->testClasses($classes);
        [$report, $frameworks] = $this->writeSuites($tests, $declarations, $migratedClasses, $output, $style);
        Filesystem::writeAtomically($output . DIRECTORY_SEPARATOR . 'migration-report.json', (string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        $outputName = $this->relative($output);
        $totals = $report['totals'];
        echo PHP_EOL . sprintf('%d test classes, %d tests migrated, %d need manual work (written as skipped tests).', $totals['files'], $totals['migrated'], $totals['skipped']) . PHP_EOL
            . "Details: $outputName/migration-report.json" . PHP_EOL . PHP_EOL;

        $this->writeConfiguration($output, $outputName, $frameworks, $style);

        return 0;
    }

    /**
     * The classes that become MicroUnit suites (everything they inherit merged in), each with the
     * file it is written to, and every class merged into one of them.
     *
     * @param list<array{ParsedClass, string}> $classes
     * @return array{list<array{ParsedClass, TestClass, string}>, array<string, true>} [class, merged class, target file relative to the output directory], lowercase merged class names
     */
    private function testClasses(array $classes): array
    {
        $index = [];
        foreach ($classes as [$class]) {
            $index[strtolower($class->fullName())] = $class;
        }
        $index = $this->withExternalBaseClasses($index);

        $found = [];
        $migratedClasses = [];
        $perFile = [];
        foreach ($classes as [$class, $root]) {
            $merged = TestClass::merge($class, $index);
            if (!$class->isAbstract && $merged->isTestCase && array_any($merged->methods, static fn(array $entry): bool => $entry[0]->isTest())) {
                $found[] = [$class, $root, $merged];
                $perFile[$class->file] = ($perFile[$class->file] ?? 0) + 1;
                foreach ($merged->mergedClasses as $name) {
                    $migratedClasses[strtolower($name)] = true;
                }
            }
        }

        $tests = [];
        foreach ($found as [$class, $root, $merged]) {
            $relative = substr($class->file, strlen($root) + 1);
            // Several test classes in one file: one file per class, named after it.
            if ($perFile[$class->file] > 1) {
                $directory = dirname($relative);
                $name = str_ends_with($class->name, 'Test') ? $class->name : $class->name . 'Test';
                $relative = ($directory === '.' ? '' : $directory . DIRECTORY_SEPARATOR) . $name . '.php';
            }
            $tests[] = [$class, $merged, $relative];
        }

        return [$tests, $migratedClasses];
    }

    /**
     * Translates and writes every test class, printing a line per file.
     *
     * @param list<array{ParsedClass, TestClass, string}> $tests
     * @param array<string, list<array{kind: string, name: string, fullName: string, code: string, namespace: ?string, uses: list<string>}>> $declarations
     * @param array<string, true> $migratedClasses
     * @return array{array<string, mixed>, list<string>} the migration report and the frameworks the classes use
     */
    private function writeSuites(array $tests, array $declarations, array $migratedClasses, string $output, TextStyle $style): array
    {
        $functions = [];
        foreach ($declarations as $fileDeclarations) {
            foreach ($fileDeclarations as $declaration) {
                if ($declaration['kind'] === 'function') {
                    $functions[strtolower($declaration['fullName'])] = true;
                }
            }
        }
        $suiteNames = self::uniqueSuiteNames(array_map(static fn(array $test): ParsedClass => $test[0], $tests));

        $report = ['generatedAt' => date(DATE_ATOM), 'suites' => []];
        $totals = ['files' => 0, 'migrated' => 0, 'skipped' => 0];
        $frameworks = [];
        $supportFiles = [];
        foreach ($tests as [$class, $merged, $relative]) {
            if ($merged->framework !== null) {
                $frameworks[] = $merged->framework;
            }
            $target = $output . DIRECTORY_SEPARATOR . $relative;
            $fileDeclarations = $declarations[$class->file] ?? [];
            $blocker = $this->supportBlocker($class, $fileDeclarations, $migratedClasses);
            if ($blocker === null && !array_key_exists($class->file, $supportFiles)) {
                $supportFiles[$class->file] = $this->writeSupportFile($class, $fileDeclarations, $migratedClasses, dirname($target));
            }
            try {
                $suite = SuiteTranslator::translate($merged, new TranslationContext($target, $blocker === null ? $supportFiles[$class->file] : null, $functions, $blocker, $suiteNames[$class->fullName()] ?? null));
            } catch (\Throwable $throwable) {
                fwrite(STDERR, $style->red('✖') . " $relative: could not migrate {$class->name} ({$throwable->getMessage()})" . PHP_EOL);
                $report['failedClasses'][$class->fullName()] = $throwable->getMessage();
                continue;
            }
            Filesystem::writeAtomically($target, $suite->code);

            $totals['files']++;
            $totals['migrated'] += count($suite->migratedTests);
            $totals['skipped'] += count($suite->skippedTests);
            $report['suites'][] = [
                'class' => $class->fullName(),
                'source' => $class->file,
                'target' => $target,
                'suite' => $suite->suite,
                'migratedTests' => $suite->migratedTests,
                'skippedTests' => $suite->skippedTests,
                'issues' => $suite->issues,
                'methods' => $suite->methods,
            ];

            $mark = $suite->skippedTests === [] && $suite->issues === [] ? $style->green('✔') : $style->yellow('!');
            echo "$mark $relative  " . $style->dim(count($suite->migratedTests) . ' migrated' . ($suite->skippedTests === [] ? '' : ', ' . count($suite->skippedTests) . ' need manual work')) . PHP_EOL;
            foreach ($suite->issues as $issue) {
                echo '    ' . $style->dim($issue) . PHP_EOL;
            }
        }
        $report['totals'] = $totals;

        return [$report, array_values(array_unique($frameworks))];
    }

    /**
     * The generated bootstrap (if needed) and microunit.config.php, unless the project already has one.
     * Part of the migrated output, so they are written on every run, like the tests themselves.
     *
     * @param list<string> $frameworks
     */
    private function writeConfiguration(string $output, string $outputName, array $frameworks, TextStyle $style): void
    {
        $phpUnit = PhpUnitConfiguration::read($this->projectDirectory);
        $framework = $frameworks[0] ?? null;
        if (count($frameworks) > 1) {
            echo $style->yellow('!') . ' The tests use ' . implode(' and ', $frameworks) . "; the configuration is set up for $framework." . PHP_EOL;
        }
        $bootstrap = $this->bootstrap($output, $outputName, $framework !== null, $phpUnit);
        $configFile = $this->projectDirectory . DIRECTORY_SEPARATOR . 'microunit.config.php';
        if (is_file($configFile)) {
            echo "microunit.config.php exists; point it at the migrated tests with ->withTestDir('$outputName') and ->withTestFilePatterns('*Test.php')." . PHP_EOL;
        } else {
            Filesystem::writeAtomically($configFile, $this->configuration($outputName, $framework === null ? null : self::INTEGRATIONS[$framework], $bootstrap, $phpUnit));
            echo $style->green('✔') . ' Created microunit.config.php for the migrated tests (from your phpunit.xml).' . PHP_EOL;
        }
        echo 'Run them with ' . $style->bold('vendor/bin/microunit') . PHP_EOL;
    }

    /**
     * The bootstrap file for the migrated suite (relative to the project), if any: phpunit.xml's
     * <env> and <ini> go into a small generated bootstrap that then requires phpunit.xml's own one.
     * With a framework integration, the environment goes to the integration instead.
     */
    private function bootstrap(string $output, string $outputName, bool $hasFramework, PhpUnitConfiguration $phpUnit): ?string
    {
        $setup = [];
        foreach ($phpUnit->ini as $name => $value) {
            $setup[] = 'ini_set(' . var_export($name, true) . ', ' . var_export($value, true) . ');';
        }
        foreach ($hasFramework ? [] : $phpUnit->environment as $name => $value) {
            $setup[] = 'putenv(' . var_export("$name=$value", true) . ');';
            $setup[] = '$_ENV[' . var_export($name, true) . '] = $_SERVER[' . var_export($name, true) . '] = ' . var_export($value, true) . ';';
        }
        if ($setup === []) {
            return $phpUnit->bootstrap;
        }
        if ($phpUnit->bootstrap !== null) {
            $setup[] = 'require ' . TranslationContext::pathExpression($output, Filesystem::resolve($this->projectDirectory, $phpUnit->bootstrap)) . ';';
        }
        Filesystem::writeAtomically(
            $output . DIRECTORY_SEPARATOR . 'bootstrap.php',
            "<?php\n\n// Generated by `microunit migrate` from phpunit.xml: what PHPUnit set up before the tests.\n\n" . implode("\n", $setup) . "\n",
        );

        return "$outputName/bootstrap.php";
    }

    /** microunit.config.php for the migrated suite, with phpunit.xml's environment for the framework integration. */
    private function configuration(string $outputName, ?string $integration, ?string $bootstrap, PhpUnitConfiguration $phpUnit): string
    {
        $imports = ['MicroUnit\Config\MicroUnitConfigBuilder'];
        $lines = ['    ->withTestDir(' . var_export($outputName, true) . ')', "    ->withTestFilePatterns('*Test.php')"];
        if ($bootstrap !== null) {
            $lines[] = '    ->withBootstrapFile(' . var_export($bootstrap, true) . ')';
        }
        if ($integration !== null) {
            $imports[] = $integration;
            $lines[] = '    ->withFramework(new ' . substr($integration, (int) strrpos($integration, '\\') + 1) . '(' . $phpUnit->environmentArgument() . '))';
        }
        sort($imports);

        return "<?php\n\n" . implode("\n", array_map(static fn(string $class): string => "use $class;", $imports)) . "\n\n"
            . "// Generated by `microunit migrate`. Every option: https://mitarnik04.github.io/MicroUnit/configuration\n"
            . "return MicroUnitConfigBuilder::create()\n" . implode("\n", $lines) . "\n    ->build();\n";
    }

    private function relative(string $path): string
    {
        $project = rtrim(str_replace('\\', '/', $this->projectDirectory), '/') . '/';
        $path = str_replace('\\', '/', $path);

        return str_starts_with($path, $project) ? substr($path, strlen($project)) : $path;
    }

    /**
     * @param list<string> $paths
     * @return array{list<string>, list<array{ParsedClass, string}>, array<string, list<array{kind: string, name: string, fullName: string, code: string, namespace: ?string, uses: list<string>}>>} files, classes with the directory they were found under, declarations per file
     */
    private function parse(array $paths, string $output): array
    {
        $files = [];
        $classes = [];
        $declarations = [];
        foreach ($paths as $path) {
            $resolved = Filesystem::resolve($this->projectDirectory, $path);
            $root = is_dir($resolved) ? rtrim($resolved, '\\/') : dirname($resolved);
            foreach (is_dir($resolved) ? Filesystem::findFiles($resolved, ['/\.php$/']) : [$resolved] as $file) {
                if (str_starts_with($file, $output . DIRECTORY_SEPARATOR) || in_array($file, $files, true)) {
                    continue;
                }
                $files[] = $file;
                try {
                    $tokens = Code::tokenize((string) file_get_contents($file));
                    $declarations[$file] = ClassParser::declarations($tokens);
                    foreach (ClassParser::classes($tokens, $file) as $class) {
                        $classes[] = [$class, $root];
                    }
                } catch (\Throwable $throwable) {
                    fwrite(STDERR, "Skipped $file: " . $throwable->getMessage() . PHP_EOL);
                }
            }
        }

        return [$files, $classes, $declarations];
    }

    /**
     * Adds base classes and traits that live outside the migrated paths (e.g. a TestCase shipped in
     * src/), located through the PSR-4 map in composer.json. Nothing is loaded or executed.
     *
     * @param array<string, ParsedClass> $index
     * @return array<string, ParsedClass>
     */
    private function withExternalBaseClasses(array $index): array
    {
        $composer = json_decode((string) @file_get_contents($this->projectDirectory . '/composer.json'), true);
        $prefixes = [];
        foreach ([$composer['autoload']['psr-4'] ?? [], $composer['autoload-dev']['psr-4'] ?? []] as $map) {
            foreach ($map as $prefix => $directories) {
                foreach ((array) $directories as $directory) {
                    $prefixes[] = [$prefix, $this->projectDirectory . '/' . trim($directory, '/')];
                }
            }
        }
        $findFile = static function (string $class) use ($prefixes): ?string {
            foreach ($prefixes as [$prefix, $directory]) {
                if (str_starts_with($class, $prefix)) {
                    $file = $directory . '/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
                    if (is_file($file)) {
                        return $file;
                    }
                }
            }

            return null;
        };

        $pending = array_values($index);
        while ($pending !== []) {
            $class = array_pop($pending);
            $names = Names::of($class->namespace, $class->uses);
            foreach ([$class->parent, ...$class->traits] as $name) {
                if ($name === null) {
                    continue;
                }
                $fullName = $names->resolve($name);
                $file = isset($index[strtolower($fullName)]) ? null : $findFile($fullName);
                if ($file === null) {
                    continue;
                }
                try {
                    $found = ClassParser::parse((string) file_get_contents($file), $file);
                } catch (\Throwable $throwable) {
                    fwrite(STDERR, "Skipped $file: " . $throwable->getMessage() . PHP_EOL);
                    continue;
                }
                foreach ($found as $foundClass) {
                    $index[strtolower($foundClass->fullName())] ??= $foundClass;
                    $pending[] = $foundClass;
                }
            }
        }

        return $index;
    }

    /**
     * Keeps what the original test file declared besides its tests (helper classes, functions) in a
     * file next to the migrated tests, in the original namespace. PHPUnit loaded them with the test
     * file; the migrated tests load this file instead.
     *
     * @param list<array{kind: string, name: string, fullName: string, code: string, namespace: ?string, uses: list<string>}> $declarations
     * @param array<string, true> $migratedClasses
     * @return string|null the support file's name, null when there is nothing to keep
     */
    private function writeSupportFile(ParsedClass $class, array $declarations, array $migratedClasses, string $directory): ?string
    {
        $kept = array_filter($declarations, static fn(array $declaration): bool => !isset($migratedClasses[strtolower($declaration['fullName'])]));
        if ($kept === []) {
            return null;
        }
        $name = basename($class->file, '.php') . '.support.php';
        $byNamespace = [];
        foreach ($kept as $declaration) {
            $byNamespace[(string) $declaration['namespace']][] = $declaration;
        }
        $useLines = static fn(array $uses): string => implode("\n", array_map(static fn(string $statement): string => "$statement;", $uses));
        if (array_keys($byNamespace) === [(string) $class->namespace]) {
            $body = ($class->namespace === null ? '' : "namespace {$class->namespace};\n\n")
                . $useLines($class->uses) . "\n\n" . implode("\n\n", array_column($kept, 'code'));
        } else {
            // Declarations from several namespaces (`namespace A; ... namespace B; ...`): one block each.
            $blocks = [];
            foreach ($byNamespace as $namespace => $declarationsInNamespace) {
                $blocks[] = 'namespace ' . ($namespace === '' ? '' : "$namespace ") . "{\n"
                    . $useLines($declarationsInNamespace[0]['uses']) . "\n\n" . implode("\n\n", array_column($declarationsInNamespace, 'code')) . "\n}";
            }
            $body = implode("\n\n", $blocks);
        }
        $code = "<?php\n\n" . ($class->hasStrictTypes ? "declare(strict_types=1);\n\n" : '')
            . '// Kept by `microunit migrate`: declared next to the tests in ' . basename($class->file) . ".\n\n"
            . $body . "\n";
        Filesystem::writeAtomically($directory . DIRECTORY_SEPARATOR . $name, $code);

        return $name;
    }

    /**
     * Helper code next to the tests that uses the test class itself (e.g. stand-in global functions
     * writing to its static properties) needs PHPUnit's test class and cannot run after migration.
     *
     * @param list<array{kind: string, name: string, fullName: string, code: string, namespace: ?string, uses: list<string>}> $declarations
     * @param array<string, true> $migratedClasses
     */
    private function supportBlocker(ParsedClass $class, array $declarations, array $migratedClasses): ?string
    {
        foreach ($declarations as $declaration) {
            if (!isset($migratedClasses[strtolower($declaration['fullName'])]) && preg_match('/\b' . preg_quote($class->name, '/') . '\s*::/', $declaration['code'])) {
                return "The helper {$declaration['kind']} {$declaration['name']}() next to the test uses the PHPUnit test class itself ({$class->name}::...); move that state out of the test class first.";
            }
        }

        return null;
    }

    /**
     * Suite names: the class name without "Test"; classes with the same name in different
     * namespaces keep the part of their namespace that tells them apart (Carbon\AddMonths,
     * CarbonImmutable\AddMonths).
     *
     * @param list<ParsedClass> $classes
     * @return array<string, string> fully qualified class => suite name, for the ambiguous ones
     */
    private static function uniqueSuiteNames(array $classes): array
    {
        $byName = [];
        foreach ($classes as $class) {
            $byName[strtolower(Code::suiteName($class->name))][] = $class;
        }
        $names = [];
        foreach ($byName as $group) {
            if (count($group) < 2) {
                continue;
            }
            $namespaces = array_map(static fn(ParsedClass $class): array => explode('\\', (string) $class->namespace), $group);
            $common = 0;
            while (array_all($namespaces, static fn(array $parts): bool => isset($parts[$common]) && $parts[$common] === $namespaces[0][$common])) {
                $common++;
            }
            foreach ($group as $index => $class) {
                $distinct = array_slice($namespaces[$index], $common);
                $names[$class->fullName()] = implode('\\', [...$distinct, Code::suiteName($class->name)]);
            }
        }

        return $names;
    }
}
