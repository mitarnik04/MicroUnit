<?php

namespace MicroUnit\Benchmarks;

/**
 * The identical test set: the project's PHPUnit tests minus the excluded tests, and the MicroUnit
 * tests migrated from exactly those. A test is excluded, from every tool alike, when
 * `microunit migrate` could not translate it, when it fails under PHPUnit on this machine, or when
 * its migrated version fails; every exclusion is recorded with its reason.
 *
 * Exclusions are as narrow as possible: a whole class, a test method, or one data set of a method
 * ("Fully\Class::method with data set #3"). Data sets are matched by position: PHPUnit and the
 * migrated MicroUnit test iterate the same data provider in the same order.
 */
final class Selection
{
    private const string DATA_SET = ' with data set ';

    /** @var array<string, string> "Fully\Class", "Fully\Class::method" or "Fully\Class::method with data set #3" => reason */
    private array $excluded = [];

    /** @var array<string, array{suite: string, class: string, methods: array<string, string>}> suite => mapping */
    private array $suites = [];

    /** @var array<string, list<string>> "Fully\Class::method" => PHPUnit's data set labels (" with data set #0", ...) in order */
    private array $phpUnitDataSets = [];

    /** @var array<string, int> "Fully\Class::method" => how many tests PHPUnit reported for it */
    private array $phpUnitTestCounts = [];

    /** @var list<array{id: string, suite: string, name: string, group: ?string}> from `microunit --list=json` */
    private array $microUnitTests = [];

    /** @param array<string, mixed> $migrationReport tests-microunit/migration-report.json */
    public function __construct(array $migrationReport)
    {
        foreach ($migrationReport['suites'] as $suite) {
            $this->suites[$suite['suite']] = ['suite' => $suite['suite'], 'class' => $suite['class'], 'methods' => $suite['methods']];
            $byTestName = array_flip($suite['methods']);
            foreach ($suite['skippedTests'] as $testName => $reasons) {
                $this->exclude($suite['class'] . '::' . ($byTestName[$testName] ?? $testName), 'not migrated automatically: ' . implode(' ', $reasons));
            }
        }
    }

    /** @param string $test "Fully\Class", "Fully\Class::method" or "Fully\Class::method with data set #3" */
    public function exclude(string $test, string $reason): void
    {
        $this->excluded[$test] ??= $reason;
    }

    /** Learns the data sets of every method from a JUnit report of a PHPUnit run (whole methods only). */
    public function learnPhpUnitDataSets(string $junitFile): void
    {
        $this->phpUnitDataSets = [];
        $this->phpUnitTestCounts = [];
        foreach (self::junitCases($junitFile) as [$class, $name]) {
            $position = strpos($name, self::DATA_SET);
            $method = $class . '::' . ($position === false ? $name : substr($name, 0, $position));
            $this->phpUnitTestCounts[$method] = ($this->phpUnitTestCounts[$method] ?? 0) + 1;
            if ($position !== false) {
                $this->phpUnitDataSets[$class . '::' . substr($name, 0, $position)][] = substr($name, $position);
            }
        }
    }

    /** @param list<array{id: string, suite: string, name: string, group: ?string}> $tests from `microunit --list=json` */
    public function learnMicroUnitTests(array $tests): void
    {
        $this->microUnitTests = $tests;
    }

    /**
     * Excludes the methods for which PHPUnit and the migrated MicroUnit test report a different number
     * of tests. PHPUnit skips a method whose requirement is not met (#[RequiresPhpExtension], ...)
     * as one test, without running its data provider; the migrated test skips every data set.
     */
    public function excludeDataSetMismatches(string $reason): int
    {
        $microUnitCounts = [];
        foreach ($this->microUnitOrigins() as $origin) {
            $method = explode(self::DATA_SET, $origin)[0];
            $microUnitCounts[$method] = ($microUnitCounts[$method] ?? 0) + 1;
        }
        $count = 0;
        foreach ($microUnitCounts as $method => $tests) {
            $phpUnitTests = $this->phpUnitTestCounts[$method] ?? null;
            if ($phpUnitTests !== null && $phpUnitTests !== $tests && !isset($this->excluded[$method])) {
                $this->exclude($method, "$reason (PHPUnit reports $phpUnitTests, the migrated test $tests)");
                $count++;
            }
        }

        return $count;
    }

    /** Excludes the PHPUnit tests (or single data sets) that failed in a JUnit report. */
    public function excludeJUnitFailures(string $junitFile, string $reason): int
    {
        $count = 0;
        foreach (self::junitCases($junitFile) as [$class, $name, $hasFailed]) {
            if ($hasFailed) {
                $this->exclude($class . '::' . $name, $reason);
                $count++;
            }
        }

        return $count;
    }

    /** Excludes the PHPUnit tests (or data sets) behind failed MicroUnit tests ("Suite::name" ids). @param list<string> $failedIds */
    public function excludeMicroUnitFailures(array $failedIds, string $reason): void
    {
        $origins = $this->microUnitOrigins();
        foreach ($failedIds as $id) {
            if (isset($origins[$id])) {
                $this->exclude($origins[$id], $reason);
            }
        }
    }

    /** --filter for PHPUnit and ParaTest: everything except the excluded tests. */
    public function phpUnitFilter(): ?string
    {
        if ($this->excluded === []) {
            return null;
        }
        // Grouped by class and method, so each name appears once: ParaTest hands the filter to its
        // workers on a command line, which cmd.exe limits to 8191 characters.
        $classes = [];
        foreach (array_keys($this->excluded) as $test) {
            [$class, $method] = explode('::', $test, 2) + [1 => null];
            if ($method === null) {
                $classes[$class] = true;
                continue;
            }
            [$method, $dataSet] = explode(self::DATA_SET, $method, 2) + [1 => null];
            if (!isset($classes[$class]) || is_array($classes[$class])) {
                $classes[$class][$method][] = $dataSet;
            }
        }
        $patterns = [];
        foreach ($classes as $class => $methods) {
            if ($methods === true) {
                $patterns[] = self::literal($class) . '::';
                continue;
            }
            $alternatives = [];
            foreach ($methods as $method => $dataSets) {
                if (in_array(null, $dataSets, true)) {
                    $alternatives[] = self::literal($method) . '\b';
                    continue;
                }
                $numbers = array_map(static fn(string $set): string => substr($set, 1), array_filter($dataSets, static fn(string $set): bool => (bool) preg_match('/^#\d+$/', $set)));
                $names = array_map(static fn(string $set): string => '.' . self::literal(substr($set, 1, -1)) . '.', array_filter($dataSets, static fn(string $set): bool => !preg_match('/^#\d+$/', $set)));
                $sets = [...($numbers === [] ? [] : ['#(?:' . implode('|', $numbers) . ')(?!\d)']), ...$names];
                // ".with.data.set." stands for " with data set ": shorter, and no spaces on the command line.
                $alternatives[] = self::literal($method) . '.with.data.set.(?:' . implode('|', $sets) . ')';
            }
            $patterns[] = self::literal($class) . '::(?:' . implode('|', $alternatives) . ')';
        }

        // Anchored: PHPUnit matches "Fully\Class::method with data set ...", and a class in the global
        // namespace must not also exclude same-named classes in other namespaces.
        return '/^(?!(?:' . implode('|', $patterns) . '))/';
    }

    /** --filter for MicroUnit: everything except the tests migrated from excluded tests. */
    public function microUnitFilter(): ?string
    {
        $ids = [];
        foreach ($this->microUnitOrigins() as $id => $origin) {
            [$class, $method] = explode('::', $origin, 2) + [1 => ''];
            $methodKey = $class . '::' . explode(self::DATA_SET, $method)[0];
            if (isset($this->excluded[$class]) || isset($this->excluded[$methodKey]) || isset($this->excluded[$origin])) {
                $ids[] = preg_quote($id, '/');
            }
        }

        return $ids === [] ? null : '/^(?!(?:' . implode('|', $ids) . ')$)/';
    }

    /**
     * $text as a regular expression that contains no spaces, quotes or other characters a shell could
     * misread: ParaTest passes the filter to its workers on a command line.
     */
    private static function literal(string $text): string
    {
        return (string) preg_replace_callback('/[^A-Za-z0-9_:#]/', static fn(array $match): string => $match[0] === '\\' ? '\\\\' : sprintf('\x%02X', ord($match[0])), $text);
    }

    /** @return array<string, string> */
    public function excluded(): array
    {
        ksort($this->excluded);

        return $this->excluded;
    }

    /**
     * The PHPUnit test behind every MicroUnit test: "Fully\Class::method", plus the data set label
     * for tests of a defineGroup() (by position within the group).
     *
     * @return array<string, string> MicroUnit id => PHPUnit test
     */
    private function microUnitOrigins(): array
    {
        $origins = [];
        $positions = [];
        foreach ($this->microUnitTests as $test) {
            $mapping = $this->suites[$test['suite']] ?? null;
            $method = $mapping === null ? null : array_search($test['group'] ?? $test['name'], $mapping['methods'], true);
            if ($method === false || $method === null) {
                continue;
            }
            $origin = $mapping['class'] . '::' . $method;
            if (($test['group'] ?? null) !== null) {
                $position = $positions[$origin] = ($positions[$origin] ?? -1) + 1;
                $origin .= $this->phpUnitDataSets[$origin][$position] ?? '';
            }
            $origins[$test['id']] = $origin;
        }

        return $origins;
    }

    /** @return list<array{string, string, bool}> class, name and whether it failed, per test case in document order */
    private static function junitCases(string $junitFile): array
    {
        $xml = (string) @file_get_contents($junitFile);
        preg_match_all('/<testcase\b([^>]*?)(\/>|>(.*?)<\/testcase>)/s', $xml, $matches, PREG_SET_ORDER);
        $cases = [];
        foreach ($matches as $match) {
            preg_match('/\bclass="([^"]+)"/', $match[1], $class);
            preg_match('/\bname="([^"]+)"/', $match[1], $name);
            if (isset($class[1], $name[1])) {
                $cases[] = [html_entity_decode($class[1]), html_entity_decode($name[1]), (bool) preg_match('/<(failure|error)\b/', $match[3] ?? '')];
            }
        }

        return $cases;
    }
}
