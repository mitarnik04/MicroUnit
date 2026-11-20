<?php

namespace MicroUnit\Integration;

use MicroUnit\Core\Test;
use MicroUnit\Core\TestContext;
use MicroUnit\Core\Tester;
use MicroUnit\Core\TestResult;

/**
 * Base for integrations that run each MicroUnit test inside the framework's own test case: a fresh
 * host per test, its setUp() before and its tearDown() after (which also verifies Mockery
 * expectations and resets framework state).
 */
abstract class HostedTestCaseIntegration implements IFrameworkIntegration
{
    private static ?object $current = null;

    /** PHPUnit's global assertion counter when the running test started. */
    private static int $phpUnitAssertionsBefore = 0;

    /** @var array<string, list<class-string>> suite => traits declared with Laravel::uses() and friends */
    private static array $suiteTraits = [];

    /** @var array<string, class-string> suite => base test case declared with useTestCase() */
    private static array $suiteTestCases = [];

    /**
     * @param class-string|null $testCase the base test case each test runs in; null = the framework default
     * @param list<class-string> $traits traits every test gets, e.g. RefreshDatabase
     * @param array<string, string> $environment environment variables (like phpunit.xml's <env>)
     */
    public function __construct(
        private readonly ?string $testCase = null,
        private readonly array $traits = [],
        private readonly array $environment = [],
    ) {}

    /** The host of the running test; used by the Laravel, Symfony and CodeIgniter proxies. */
    public static function current(): object
    {
        return self::$current ?? throw new \LogicException(
            'No framework test is running. Configure ->withFramework(...) in microunit.config.php and use this inside a test.',
        );
    }

    /** @param list<mixed> $arguments */
    public static function forward(string $method, array $arguments): mixed
    {
        return self::current()->callForMicroUnit($method, $arguments);
    }

    /**
     * Gives the tests of one suite additional traits, like `use RefreshDatabase;` in a PHPUnit class.
     *
     * @param class-string ...$traits
     */
    public static function useTraitsFor(Tester $tester, string ...$traits): void
    {
        self::$suiteTraits[$tester->suite] = array_values(array_unique([...self::$suiteTraits[$tester->suite] ?? [], ...$traits]));
    }

    /**
     * Runs the tests of one suite in another base test case, e.g. one with CodeIgniter's $seed and
     * $refresh set, or a Laravel test case with extra helpers.
     *
     * @param class-string $testCase
     */
    public static function useTestCaseFor(Tester $tester, string $testCase): void
    {
        self::$suiteTestCases[$tester->suite] = $testCase;
    }

    /** @return class-string the framework's base test case, used when none is configured */
    abstract protected function defaultTestCase(): string;

    #[\Override]
    public function boot(string $projectDirectory): void
    {
        foreach ($this->environment as $name => $value) {
            putenv("$name=$value");
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }

    #[\Override]
    public function beforeTest(Test $test): void
    {
        $traits = array_values(array_unique([...$this->traits, ...self::$suiteTraits[$test->suite] ?? []]));
        $host = TestCaseHost::create(self::$suiteTestCases[$test->suite] ?? $this->testCase ?? $this->defaultTestCase(), $traits);
        self::$current = $host;
        self::$phpUnitAssertionsBefore = self::phpUnitAssertionCount();
        $host->setUpForMicroUnit();
    }

    #[\Override]
    public function afterTest(Test $test, TestResult $result): void
    {
        $host = self::$current;
        self::$current = null;
        $host?->tearDownForMicroUnit();
        // Static PHPUnit assertions count globally; Mockery expectations are added to the test case itself.
        $hostAssertions = match (true) {
            $host !== null && method_exists($host, 'numberOfAssertionsPerformed') => $host->numberOfAssertionsPerformed(),
            $host !== null && method_exists($host, 'getNumAssertions') => $host->getNumAssertions(),
            default => 0,
        };
        TestContext::countAssertions(self::phpUnitAssertionCount() - self::$phpUnitAssertionsBefore + $hostAssertions);
    }

    private static function phpUnitAssertionCount(): int
    {
        return class_exists('PHPUnit\Framework\Assert', false) ? ('PHPUnit\Framework\Assert')::getCount() : 0;
    }
}
