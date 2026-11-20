<?php

namespace MicroUnit\Core;

/**
 * Defines the tests of one suite. Obtain it with TestSetup::getTester('Suite') — tests are only
 * registered here and executed later by the runner.
 */
final class Tester
{
    /** @var list<Test> */
    private array $tests = [];

    /** @var array<string, int> how often each test name was used (duplicates get unique ids) */
    private array $nameUsage = [];

    private ?\Closure $setUp = null;

    private ?\Closure $tearDown = null;

    private ?\Closure $setUpOnce = null;

    private ?\Closure $tearDownOnce = null;

    /** @var array<string, Fixture> */
    private array $fixtures = [];

    public function __construct(
        public readonly string $suite,
    ) {}

    /**
     * Declares a named resource. Every test (or fixture factory) with a parameter of that name
     * receives it: created on first use, cleaned up after the test — or after the suite when shared.
     *
     *     $tester->fixture('db', fn() => Database::sqlite('schema.sql'));
     *     $tester->define('stores_orders', function (Database $db) { ... });
     *
     * @param callable(mixed ...$otherFixtures): mixed $factory may ask for other fixtures by parameter name
     * @param (callable(mixed $value): void)|null $cleanup
     * @param bool $shared create one instance for the whole suite instead of one per test
     */
    public function fixture(string $name, callable $factory, ?callable $cleanup = null, bool $shared = false): void
    {
        $this->fixtures[$name] = new Fixture($name, $factory(...), $cleanup === null ? null : $cleanup(...), $shared);
    }

    /**
     * Runs once before the first test of the suite — the place for expensive shared resources of
     * integration tests (database connections, servers, containers). Its return value is passed to
     * setUp(); without a setUp() it is passed to every test as its first argument.
     *
     * @param callable(): mixed $setUpOnce
     */
    public function setUpOnce(callable $setUpOnce): void
    {
        $this->setUpOnce = $setUpOnce(...);
    }

    /**
     * Runs once after the last test of the suite and receives setUpOnce()'s return value.
     *
     * @param callable(mixed $sharedFixture): void $tearDownOnce
     */
    public function tearDownOnce(callable $tearDownOnce): void
    {
        $this->tearDownOnce = $tearDownOnce(...);
    }

    /**
     * Runs before every test of the suite and receives setUpOnce()'s value (if any). A non-null
     * return value is passed to each test as its first argument (and to tearDown as its second).
     * Parameters named like a fixture receive that fixture: the same instance the test gets. Taken
     * by reference (`function (?Clock &$clock)`), the fixture can be replaced for this test.
     *
     * @param callable(mixed $sharedFixture): mixed $setUp
     */
    public function setUp(callable $setUp): void
    {
        $this->setUp = $setUp(...);
    }

    /**
     * Runs after every test of the suite, also when the test failed. Parameters named like a
     * fixture receive that fixture.
     *
     * @param callable(TestResult $result, mixed $setUpResult): void $tearDown
     */
    public function tearDown(callable $tearDown): void
    {
        $this->tearDown = $tearDown(...);
    }

    /** @param mixed ...$args passed to $test (after the setUp result, if any) */
    public function define(string $name, callable $test, mixed ...$args): void
    {
        $caller = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0];
        $this->register($name, $test(...), array_values($args), $caller['file'] ?? null, $caller['line'] ?? null);
    }

    /**
     * Defines one test per data set, named "{$baseName}_{name}". A data set is either a TestCase or
     * an array of arguments; its key names it:
     *
     * $tester->defineGroup('adds', fn (int $a, int $b, int $sum) => Assert::exact($sum, $a + $b), [
     *     'small numbers' => [1, 2, 3],
     *     'negatives' => [-1, -2, -3],
     * ]);
     *
     * Any iterable works, so a generator function can supply the data sets.
     *
     * @param iterable<int|string, TestCase|array<mixed>> $cases unnamed (integer-keyed) data sets are numbered from 1
     */
    public function defineGroup(string $baseName, callable $test, iterable $cases): void
    {
        $caller = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0];
        $callable = $test(...);
        $position = 0;
        foreach ($cases as $key => $case) {
            $position++;
            if (!$case instanceof TestCase) {
                $case = new TestCase(is_string($key) ? $key : (string) $position, $case);
            }
            $args = is_array($case->params) ? array_values($case->params) : [$case->params];
            $this->register("{$baseName}_{$case->testNameSuffix}", $callable, $args, $caller['file'] ?? null, $caller['line'] ?? null, $baseName);
        }
    }

    /** @return list<Test> */
    public function getTests(): array
    {
        return $this->tests;
    }

    public function getSetUp(): ?\Closure
    {
        return $this->setUp;
    }

    public function getTearDown(): ?\Closure
    {
        return $this->tearDown;
    }

    /** @return array<string, Fixture> */
    public function getFixtures(): array
    {
        return $this->fixtures;
    }

    public function getSetUpOnce(): ?\Closure
    {
        return $this->setUpOnce;
    }

    public function getTearDownOnce(): ?\Closure
    {
        return $this->tearDownOnce;
    }

    /** @param list<mixed> $args */
    private function register(string $name, \Closure $callable, array $args, ?string $file, ?int $line, ?string $group = null): void
    {
        $usage = $this->nameUsage[$name] = ($this->nameUsage[$name] ?? 0) + 1;
        $id = $this->suite . '::' . $name . ($usage > 1 ? "#$usage" : '');

        $this->tests[] = new Test($id, $this->suite, $name, $callable, $args, $file, $line, $group);
    }
}
