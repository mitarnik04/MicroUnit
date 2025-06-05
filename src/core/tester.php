<?php
require_once __DIR__ . '/test.php';
require_once __DIR__ . '/test-result.php';
require_once __DIR__ . '/test-case.php';

class Tester
{
    public function __construct(
        private bool $stopOnFailure
    ) {}

    /** @var array<Test> */
    private array $tests = [];


    /** @var callable() */
    private $setUp;

    /** @var callable(mixed $testResult, mixed $setUpResult): void */
    private $teardown;

    /**
     * Define a setUp function that is called once **BEFORE** every test
     * @return callable(): void
     */
    public function setUp(callable $setUp)
    {
        $this->setUp = $setUp;
    }

    /** 
     * Define a teardown function that is called once **AFTER** every test.
     * The output of setup as well as the test result will be passed as arguments to the teardown method
     * @return callable(mixed $testResult, mixed $setUpResult): void 
     */
    public function teardown(callable $teardown)
    {
        $this->teardown = $teardown;
    }


    public function define(string $name, callable $test, ...$args): void
    {
        $this->tests[] = new Test($name, $test, $args);
    }

    /** @param array<TestCase> $cases */
    public function defineGroup(string $baseName, callable $testCallable, array $cases): void
    {
        foreach ($cases as $case) {
            $testName = "{$baseName}_{$case->testNameSuffix}";
            if (is_array($case->params)) {
                $this->tests[] = new Test($testName, $testCallable, ...$case->params);
            } else {
                $this->tests[] = new Test($testName, $testCallable, $case->params);
            }
        }
    }

    /** @return array<TestResult> */
    public function run(): array
    {
        $results = [];
        foreach ($this->tests as $test) {
            $result = $this->runSingleTest($test);
            $results[] = $result;
            if ($this->stopOnFailure && $result->isError) {
                return $results;
            }
        }

        return $results;
    }

    private function runSingleTest(Test $test): TestResult
    {
        try {
            $setUpResult = isset($this->setUp) ? ($this->setUp)() : null;
            $args = is_array($test->args) ? $test->args : [$test->args];

            //If setUpResult exists, add it as first argument
            if ($setUpResult !== null) {
                array_unshift($args, $setUpResult);
            }

            $startTime = microtime(true);

            $result = ($test->testCallable)(...$args);

            $endTime = microtime(true);

            if (isset($this->teardown)) {
                ($this->teardown)($result, $setUpResult);
            }
            return TestResult::success($test->name, $result, $endTime - $startTime);
        } catch (Exception $e) {
            $endTime = microtime(true);
            return TestResult::failiureFromException($test->name, $e, $endTime - $startTime);
        }
    }
}
