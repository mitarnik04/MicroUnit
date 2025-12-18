<?php

namespace MicroUnit\Core;

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
    private $tearDown;

    /**
     * Define a setUp function that is called once **BEFORE** every test
     * @return callable(): void
     */
    public function setUp(callable $setUp)
    {
        $this->setUp = $setUp;
    }

    /** 
     * Define a tearDown function that is called once **AFTER** every test.
     * The output of setup as well as the test result will be passed as arguments to the tearDown method
     * @return callable(mixed $testResult, mixed $setUpResult): void 
     */
    public function tearDown(callable $tearDown)
    {
        $this->tearDown = $tearDown;
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
            $this->tests[] = new Test($testName, $testCallable, $case->params);
        }
    }

    /** @return array<TestResult> */
    public function run(): array
    {
        $results = [];
        foreach ($this->tests as $test) {
            $result = $this->runSingleTest($test);
            $results[] = $result;
            if ($this->stopOnFailure && !$result->isSuccess) {
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

            if (isset($this->tearDown)) {
                ($this->tearDown)($result, $setUpResult);
            }
            return TestResult::success($test->name, $result, $endTime - $startTime);
        } catch (\Exception $e) {
            $endTime = microtime(true);
            return TestResult::failureFromException($test->name, $e, $endTime - $startTime);
        }
    }
}
