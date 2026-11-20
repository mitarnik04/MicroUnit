<?php

namespace MicroUnit\Migrate;

/**
 * Translates a merged PHPUnit test class into a MicroUnit test file:
 *
 * - instance properties become fixtures (from their setUp() assignment or default value), static
 *   properties assigned in setUpBeforeClass() become shared fixtures;
 * - setUp()/tearDown()/setUpBeforeClass()/tearDownAfterClass() become the Tester hooks;
 * - every other method becomes a function in the file's namespace; properties it uses are passed in;
 * - tests become define()/defineGroup() closures that receive the fixtures they use;
 * - data providers, #[TestWith], expectException*() and #[Requires*] get their MicroUnit form.
 *
 * Tests that use something without a translation are emitted as skipped tests that keep the
 * original code as a comment, so nothing is lost and the explorer lists what is left to do.
 */
final class SuiteTranslator
{
    private const array LIFECYCLE = ['setup', 'teardown', 'setupbeforeclass', 'teardownafterclass'];

    private const array MOCKERY_TRAITS = ['mockery\adapter\phpunit\mockeryphpunitintegration'];

    /** @var array<string, list<array{string, bool}>> lowercase helper => [property, assigned] state parameters */
    private array $state = [];

    /** @var array<string, string> alias => fully qualified class */
    private array $imports = [];

    /** @var list<string> */
    private array $issues = [];

    /**
     * Fixtures by name: a property, or a local variable of setUp() that several fixtures share.
     *
     * @var array<string, array{string, string, bool, bool}> name => [factory code, type, shared, eager]
     */
    private array $fixtures = [];

    /** @var array<string, list<string>> fixture => tearDownAfterClass() statements that become its cleanup */
    private array $cleanups = [];

    /**
     * Helpers that only set expectations, e.g. expectExceptionMessageExact($m) calling
     * $this->expectExceptionMessageMatches(...): they are expanded where a test calls them.
     *
     * @var array<string, array{list<string>, list<array{string, string}>}> lowercase helper => [parameter names, [expect method, argument code]]
     */
    private array $expectationHelpers = [];

    /** @var array<string, true> fixtures that only come from a property's default value (setUp() may still replace them) */
    private array $defaultFixtures = [];

    /** @var list<string> mock expectations set up in setUp(), checked after every test */
    private array $setUpChecks = [];

    /** Short names MicroUnit's generated code uses; a project class with the same name gets an alias. */
    private const array RESERVED_NAMES = [
        'assert' => 'MicroUnit\Assertion\Assert',
        'testsetup' => 'MicroUnit\Setup\TestSetup',
        'mockbuilder' => 'MicroUnit\Mock\MockBuilder',
        'micromock' => 'MicroUnit\Mock\MicroMock',
        'assertmock' => 'MicroUnit\Assertion\AssertMock',
    ];

    /** @var array<string, string> lowercase function name => fully qualified function */
    private array $functionImports = [];

    /** @var array<int, array{Names, array<string, string>}> by owner object id: its names and the aliases to rename */
    private array $ownerNames = [];

    /** @var array<string, string> "owner id type" => the type as the migrated file writes it */
    private array $typeTexts = [];

    /** @var array<string, list<\PhpToken>> method body => its tokens */
    private array $bodyTokens = [];

    private function __construct(
        private readonly TestClass $class,
        private readonly TranslationContext $context,
    ) {}

    public static function translate(TestClass $class, ?TranslationContext $context = null): MigratedSuite
    {
        return new self($class, $context ?? new TranslationContext($class->class->file))->run();
    }

    private function run(): MigratedSuite
    {
        $class = $this->class;
        $this->issues = $class->issues;
        [$tests, $functions, $befores, $afters] = $this->classify();
        $this->findExpectationHelpers($functions);
        $this->analyzeState($functions);
        if (array_any($this->class->frameworkTraits, static fn(string $trait): bool => in_array(strtolower($trait), self::MOCKERY_TRAITS, true))) {
            $this->imports['Mockery'] = 'Mockery';
            $this->setUpChecks[] = 'Mockery::close(); // verifies Mockery expectations, as MockeryPHPUnitIntegration did';
        }

        $this->addDefaultValueFixtures();
        $callsOf = fn(array $helpers): array => array_map(fn(string $helper): string => $this->helperCallCode($helper) . ';', $helpers);
        $setUp = $this->lifecycle('setup', isShared: false, extraStatements: $callsOf($befores));
        $tearDown = $this->lifecycle('teardown', isShared: false, extraStatements: $callsOf($afters));
        $setUpOnce = $this->lifecycle('setupbeforeclass', isShared: true);
        $tearDownOnce = $this->lifecycle('teardownafterclass', isShared: true);

        $migrated = [];
        $methods = [];
        $skipped = [];
        $testCode = [];
        foreach ($tests as [$method, $owner]) {
            [$code, $issues] = $this->context->blocker === null ? $this->test($method, $owner) : ['', [$this->context->blocker]];
            $name = Code::testName($method->name);
            $methods[$method->name] = $name;
            if ($issues === []) {
                $migrated[] = $name;
                $testCode[] = $code;
            } else {
                $skipped[$name] = $issues;
                $testCode[] = $this->skippedTest($name, $method, $issues);
            }
        }

        $functionCode = [];
        foreach ($functions as $lower => [$method, $owner]) {
            if (!isset($this->expectationHelpers[$lower])) {
                $functionCode[] = $this->function($lower, $method, $owner);
            }
        }

        $suite = $this->context->suiteName ?? Code::suiteName($class->class->name);
        $hooks = array_values(array_filter([$this->traitUses(), $this->fixtureCode(), $setUpOnce, $tearDownOnce, $setUp, $tearDown]));
        $constants = $this->constantCode();
        $sections = [
            $this->header(),
            $constants,
            "\$tester = TestSetup::getTester('" . addslashes($suite) . "');",
            ...$hooks,
            ...$testCode,
            ...$functionCode,
        ];

        return new MigratedSuite($suite, implode("\n\n", array_filter($sections, static fn(string $section): bool => $section !== '')) . "\n", $migrated, $skipped, $this->issues, $methods);
    }

    /** @return array{list<array{ParsedMethod, ParsedClass}>, array<string, array{ParsedMethod, ParsedClass}>, list<string>, list<string>} */
    private function classify(): array
    {
        $tests = [];
        $functions = [];
        $befores = [];
        $afters = [];
        foreach ($this->class->methods as $lower => [$method, $owner]) {
            if ($method->body === null || in_array($lower, self::LIFECYCLE, true)) {
                continue;
            }
            if ($method->isTest()) {
                $tests[] = [$method, $owner];
                continue;
            }
            if ($method->hasAttribute('Before') || $method->annotation('before') !== []) {
                $befores[] = $method->name;
            }
            if ($method->hasAttribute('After') || $method->annotation('after') !== []) {
                $afters[] = $method->name;
            }
            $functions[$lower] = [$method, $owner];
        }
        $pending = array_keys($functions);
        while ($pending !== []) {
            $key = array_pop($pending);
            [$lower, $level] = explode('#', $key) + [1 => '0'];
            $method = $functions[$key][0];
            $next = (int) $level + 1;
            $parent = $this->class->definitions[$lower][$next] ?? null;
            if ($parent !== null && !isset($functions["$lower#$next"]) && preg_match('/parent::\s*' . preg_quote($method->name, '/') . '\s*\(/i', (string) $method->body)) {
                $functions["$lower#$next"] = $parent;
                $pending[] = "$lower#$next";
            }
        }

        return [$tests, $functions, $befores, $afters];
    }

    /**
     * Which properties each helper needs (directly or through other helpers) and whether it assigns them.
     *
     * @param array<string, array{ParsedMethod, ParsedClass}> $functions
     */
    private function analyzeState(array $functions): void
    {
        $direct = [];
        $calls = [];
        foreach ($functions as $lower => [$method, $owner]) {
            $scan = $this->rewriter($owner, $method, static fn() => [], self::levelOf($lower));
            $tokens = $this->tokensOf((string) $method->body);
            $scan->rewrite($tokens, 0, count($tokens) - 1);
            $direct[$lower] = [];
            foreach (array_keys($scan->usedProperties) as $property) {
                if (isset($this->class->properties[$property])) {
                    $direct[$lower][$property] = isset($scan->assignedProperties[$property]);
                }
            }
            $calls[$lower] = array_keys($scan->calledHelpers);
        }

        $state = $direct;
        do {
            $changed = false;
            foreach ($calls as $lower => $callees) {
                foreach ($callees as $callee) {
                    foreach ($state[$callee] ?? [] as $property => $isAssigned) {
                        if (!isset($state[$lower][$property]) || ($isAssigned && !$state[$lower][$property])) {
                            $state[$lower][$property] = $isAssigned || ($state[$lower][$property] ?? false);
                            $changed = true;
                        }
                    }
                }
            }
        } while ($changed);

        foreach ($state as $lower => $properties) {
            ksort($properties);
            $this->state[$lower] = array_map(null, array_keys($properties), array_values($properties));
        }
    }

    /**
     * setUp(), tearDown(), setUpBeforeClass() or tearDownAfterClass(), with parent:: calls inlined.
     * Leading `$this->property = ...;` statements become fixtures; in tearDownAfterClass(),
     * statements about a single shared fixture become its cleanup.
     *
     * @param list<string> $extraStatements code to run at the end (#[Before]/#[After] helper calls)
     */
    private function lifecycle(string $name, bool $isShared, array $extraStatements = []): string
    {
        $statements = $this->lifecycleStatements($name, 0);
        if ($name === 'teardown') {
            $extraStatements = [...$extraStatements, ...$this->setUpChecks];
        }
        if ($statements === [] && $extraStatements === []) {
            return '';
        }

        /** @var list<string> $history code of the statements so far that stayed statements */
        $history = [];
        /** @var list<int> $body history indexes of the hook's statements */
        $body = [];
        /** @var list<int> $pending history indexes waiting for the next fixture */
        $pending = [];
        /** @var array<int, string> $claimed history index => fixture whose factory runs the statement */
        $claimed = [];
        $assigned = [];
        $factories = [];
        $isSetUp = str_starts_with($name, 'setup');
        foreach ($statements as [$tokens, $from, $to, $method, $owner]) {
            // `unset($this->client);` or `$this->client = null;` in tearDown(): fixtures are created
            // per test anyway, and asking for one here would only create it again.
            if (!$isSetUp && preg_match('/^(unset\s*\(\s*(\$this->\w+|(self|static)::\$\w+)(\s*,\s*(\$this->\w+|(self|static)::\$\w+))*\s*,?\s*\)|(\$this->\w+|(self|static)::\$\w+)\s*=\s*null)\s*;$/', trim(Code::text($tokens, $from, $to))) === 1) {
                continue;
            }
            $assignment = $isSetUp ? $this->propertyAssignment($tokens, $from, $to, $isShared) : null;
            $rewriter = $this->rewriter($owner, $method);
            if ($assignment !== null) {
                [$property, $expressionFrom] = $assignment;
                $expression = $this->mocks(Code::reindent(trim($rewriter->rewrite($tokens, $expressionFrom, $to - 1)), self::baseIndentation($tokens)), "$name()");
                $this->flagExpectationHelpers($rewriter);
                $this->addIssues($rewriter, "$name()");
                $locals = array_diff($this->variablesIn($expression), array_keys($this->fixtures), array_keys($this->class->properties));
                $needed = array_diff_key(self::statementsAssigning($history, $locals), array_flip($pending));
                $isEager = $pending !== [] || $needed !== [] || !$this->isPure($tokens, $expressionFrom, $to - 1);
                $pendingCode = array_map(static fn(int $index): string => $history[$index], $pending);
                foreach ($pending as $index) {
                    $claimed[$index] = $property;
                }
                $computing = $this->factoryStatements($property, $needed, implode("\n", [...$pendingCode, $expression]), $history, $claimed, $factories, $isShared, $name);
                $factories[$property] = [...$pendingCode, ...$computing, "return $expression;"];
                $this->addFixture($property, $owner, $factories[$property], $isShared, eager: $isEager);
                $pending = [];
                continue;
            }
            $code = $this->mocks(Code::reindent(trim($rewriter->rewrite($tokens, $from, $to)), self::baseIndentation($tokens)), "$name()", $name === 'setup');
            $this->flagExpectationHelpers($rewriter);
            $this->addIssues($rewriter, "$name()");
            $touched = array_keys($rewriter->usedProperties);
            // `$this->items = []; foreach (...) { $this->items[] = ...; }`: the statements that only
            // complete a fixture created above belong to its factory.
            if ($isSetUp && $code !== '' && count($touched) === 1 && isset($factories[$touched[0]])) {
                $property = $touched[0];
                $locals = array_diff($this->variablesIn($code), [$property], array_keys($this->fixtures), array_keys($this->class->properties));
                $factory = $factories[$property];
                $return = array_pop($factory);
                if ($return !== "return \$$property;") {
                    $factory[] = "\$$property = " . substr($return, strlen('return ')) ;
                }
                $computing = $this->factoryStatements($property, self::statementsAssigning($history, $locals), $code, $history, $claimed, $factories, $isShared, $name);
                $factories[$property] = [...$factory, ...$computing, $code, "return \$$property;"];
                $this->addFixture($property, $owner, $factories[$property], $isShared, eager: true);
                continue;
            }
            if ($name === 'teardownafterclass' && $this->becomesCleanup($code)) {
                continue;
            }
            $assigned += $rewriter->assignedProperties;
            if ($code === '') {
                continue;
            }
            $history[] = $code;
            if ($isSetUp && !$isShared && array_keys($rewriter->usedProperties) === [] && $body === [] && $this->fixtures !== []) {
                $pending[] = count($history) - 1;
                continue;
            }
            $body = [...$body, ...$pending, count($history) - 1];
            $pending = [];
        }
        $body = [
            ...array_map(static fn(int $index): string => $history[$index], self::withoutClaimed([...$body, ...$pending], $history, $claimed)),
            ...$extraStatements,
        ];

        if ($isShared) {
            if ($body === []) {
                return '';
            }
            $unbound = array_values(array_filter($this->usedVariables($body), fn(string $variable): bool => isset($this->fixtures[$variable])));
            if ($unbound !== []) {
                $this->issues[] = "$name() uses " . implode(', ', array_map(static fn(string $variable): string => "\$$variable", $unbound))
                    . ', which became shared fixtures; setUpOnce()/tearDownOnce() cannot receive fixtures, so move that code into the fixture or its cleanup.';
            }
            $hook = $name === 'setupbeforeclass' ? 'setUpOnce' : 'tearDownOnce';

            return "\$tester->$hook(function (): void {\n" . $this->indentLines($body) . "\n});";
        }

        // A fixture assigned in setUp()/tearDown() is taken by reference: tests see the new value.
        $parameters = [];
        foreach ($this->usedVariables($body) as $property) {
            if (isset($this->fixtures[$property])) {
                $parameters[$property] = $this->parameter($property, byReference: isset($assigned[$property]));
            }
        }
        if ($name === 'setup') {
            foreach ($this->fixtures as $property => [, , $shared, $eager]) {
                if ($eager && !$shared) {
                    $parameters[$property] ??= $this->parameter($property);
                }
            }
        }
        if ($body === [] && $parameters === []) {
            return '';
        }
        $hook = $name === 'setup' ? 'setUp' : 'tearDown';
        $code = $body === [] ? "    // Creates the fixtures above before every test, as setUp() did.\n" : $this->indentLines($body) . "\n";

        return "\$tester->$hook(function (" . implode(', ', $parameters) . "): void {\n" . $code . '});';
    }

    /**
     * The earlier statements a fixture's factory needs ($needed: history index => code), minus
     * the ones its factory already has. A statement another fixture's factory runs is not copied:
     * the local variable it computes becomes a fixture both of them receive, so they share the
     * object as they did in setUp().
     *
     * @param array<int, string> $needed
     * @param string $code the factory's own code that uses the statements' variables
     * @param list<string> $history
     * @param array<int, string> $claimed history index => fixture
     * @param array<string, list<string>> $factories
     * @param string $hook the lifecycle method, for the issue
     * @return list<string>
     */
    private function factoryStatements(string $property, array $needed, string $code, array $history, array &$claimed, array &$factories, bool $isShared, string $hook): array
    {
        $own = [];
        $shared = [];
        foreach ($needed as $index => $statement) {
            if (!isset($claimed[$index])) {
                $own[$index] = $statement;
            } elseif ($claimed[$index] !== $property) {
                $shared[$index] = $statement;
            }
        }
        if ($shared !== []) {
            $local = $this->sharedLocal($property, $shared, [$code, ...$own], $history, $claimed, $factories);
            if ($local === null) {
                $others = array_values(array_unique(array_map(static fn(int $index): string => $claimed[$index], array_keys($shared))));
                $this->issues[] = "$hook() computes a value the fixtures \$$property and \$" . implode(', $', $others) . ' share; each fixture now computes its own, check that they do not need the same object.';
                $own += $shared;
                ksort($own);
            } else {
                $own = array_diff_key($own, array_flip($this->addSharedLocal($local[0], $local[1], $history, $claimed, $factories, $isShared)));
            }
        }
        foreach (array_keys($own) as $index) {
            $claimed[$index] = $property;
        }

        return array_values($own);
    }

    /**
     * The local variable that statements already claimed by other fixtures compute for this one,
     * with all the statements that compute it; null when it is not exactly one variable that can
     * be taken out of the other factories.
     *
     * @param string $property the fixture whose factory needs the statements
     * @param array<int, string> $shared
     * @param list<string> $users the code of the new factory that needs the variable
     * @param list<string> $history
     * @param array<int, string> $claimed
     * @param array<string, list<string>> $factories
     * @return array{string, list<int>}|null
     */
    private function sharedLocal(string $property, array $shared, array $users, array $history, array $claimed, array $factories): ?array
    {
        $candidates = array_values(array_intersect(
            array_unique(array_merge(...array_map(self::assignedVariables(...), array_values($shared)))),
            $this->variablesIn(implode("\n", $users)),
        ));
        if (count($candidates) !== 1 || isset($this->fixtures[$candidates[0]]) || isset($this->class->properties[$candidates[0]])) {
            return null;
        }
        $variable = $candidates[0];
        $statements = self::statementsAssigning($history, [$variable]);
        $others = array_diff(array_unique(array_merge(...array_map(self::assignedVariables(...), array_values($statements)))), [$variable]);
        $affected = array_unique(array_intersect_key($claimed, $statements));
        if (in_array($property, $affected, true)) {
            return null;
        }
        foreach ([...array_map(static fn(string $fixture): array => array_diff($factories[$fixture] ?? [], $statements), $affected), $users] as $remaining) {
            if (array_intersect($this->variablesIn(implode("\n", $remaining)), $others) !== []) {
                return null;
            }
        }

        return [$variable, array_keys($statements)];
    }

    /**
     * Makes $variable a fixture of its own, created by the given statements, and takes those
     * statements out of the factories that ran them.
     *
     * @param list<int> $indexes
     * @param list<string> $history
     * @param array<int, string> $claimed
     * @param array<string, list<string>> $factories
     * @return list<int> the statements' history indexes
     */
    private function addSharedLocal(string $variable, array $indexes, array $history, array &$claimed, array &$factories, bool $isShared): array
    {
        $statements = array_map(static fn(int $index): string => $history[$index], $indexes);
        $names = array_keys($this->fixtures);
        $position = count($names);
        foreach ($indexes as $index) {
            $fixture = $claimed[$index] ?? null;
            $found = $fixture === null ? false : array_search($history[$index], $factories[$fixture], true);
            if ($found !== false) {
                array_splice($factories[(string) $fixture], $found, 1);
                $this->fixtures[(string) $fixture][0] = implode("\n", $factories[(string) $fixture]);
                // Listed before the fixtures that use it.
                $position = min($position, (int) array_search($fixture, $names, true));
            }
            $claimed[$index] = $variable;
        }
        $factory = count($statements) === 1 && preg_match('/^\$' . $variable . '\s*=(?![=>])\s*(.*);$/s', $statements[0], $match) === 1
            ? ['return ' . $match[1] . ';']
            : [...$statements, "return \$$variable;"];
        $factories[$variable] = $factory;
        $this->fixtures = array_slice($this->fixtures, 0, $position, true)
            + [$variable => [implode("\n", $factory), '', $isShared, false]]
            + array_slice($this->fixtures, $position, null, true);

        return $indexes;
    }

    /**
     * The hook's statements without those a fixture factory now runs, unless other statements of
     * the hook still use what they compute.
     *
     * @param list<int> $indexes
     * @param list<string> $history
     * @param array<int, string> $claimed
     * @return list<int>
     */
    private static function withoutClaimed(array $indexes, array $history, array $claimed): array
    {
        $kept = [];
        foreach ($indexes as $index) {
            $assigned = self::assignedVariables($history[$index]);
            $isLocal = isset($claimed[$index]) && !array_any($assigned, static fn(string $variable): bool => $variable === 'GLOBALS' || str_starts_with($variable, '_'));
            if ($isLocal) {
                $others = array_map(static fn(int $other): string => $history[$other], array_filter($indexes, static fn(int $other): bool => $other !== $index));
                preg_match_all('/(?<![\w>:$])\$(\w+)/', implode("\n", $others), $used);
                if (array_intersect($assigned, $used[1]) === []) {
                    continue;
                }
            }
            $kept[] = $index;
        }

        return $kept;
    }

    /** tearDownAfterClass() code about one shared fixture and nothing else becomes that fixture's cleanup. */
    private function becomesCleanup(string $code): bool
    {
        $variables = $this->variablesIn($code);
        if (count($variables) !== 1 || !($this->fixtures[$variables[0]][2] ?? false)) {
            return false;
        }
        $this->cleanups[$variables[0]][] = $code;

        return true;
    }

    /**
     * Top-level statements of a lifecycle method, parent::name() calls replaced by the parent's statements.
     *
     * @return list<array{list<\PhpToken>, int, int, ParsedMethod, ParsedClass}>
     */
    private function lifecycleStatements(string $name, int $level): array
    {
        $definition = $this->class->definitions[$name][$level] ?? null;
        if ($definition === null) {
            return [];
        }
        [$method, $owner] = $definition;
        $tokens = $this->tokensOf($this->renameVariables((string) $method->body, $this->localConflicts((string) $method->body)));
        $statements = [];
        foreach (Code::statements($tokens) as [$from, $to]) {
            if (preg_match('/^parent::' . preg_quote($method->name, '/') . '\s*\(\s*\)\s*;$/i', trim(Code::text($tokens, $from, $to)))) {
                $statements = [...$statements, ...$this->lifecycleStatements($name, $level + 1)];
                continue;
            }
            $statements[] = [$tokens, $from, $to, $method, $owner];
        }

        return $statements;
    }

    /**
     * `$this->property = expression;` (or self::$property in setUpBeforeClass) => [property, expression start].
     *
     * @param list<\PhpToken> $tokens
     * @return array{string, int}|null
     */
    private function propertyAssignment(array $tokens, int $from, int $to, bool $isShared): ?array
    {
        $first = $tokens[$from]->isIgnorable() ? Code::next($tokens, $from) : $from;
        if ($first === null) {
            return null;
        }
        if ($isShared) {
            $colon = Code::next($tokens, $first);
            $nameIndex = $colon === null ? null : Code::next($tokens, $colon);
            $isMatch = in_array(strtolower($tokens[$first]->text), ['self', 'static'], true) && $tokens[(int) $colon]->id === T_DOUBLE_COLON
                && $nameIndex !== null && $tokens[$nameIndex]->id === T_VARIABLE;
            $property = $isMatch ? substr($tokens[(int) $nameIndex]->text, 1) : null;
        } else {
            $arrow = Code::next($tokens, $first);
            $nameIndex = $arrow === null ? null : Code::next($tokens, $arrow);
            $isMatch = $tokens[$first]->text === '$this' && $tokens[(int) $arrow]->id === T_OBJECT_OPERATOR && $nameIndex !== null && $tokens[$nameIndex]->id === T_STRING;
            $property = $isMatch ? $tokens[(int) $nameIndex]->text : null;
        }
        if ($property === null || !isset($this->class->properties[$property]) || (isset($this->fixtures[$property]) && !isset($this->defaultFixtures[$property]))) {
            return null;
        }
        $equals = Code::next($tokens, (int) $nameIndex);

        return $equals !== null && Code::char($tokens[$equals]) === '=' && Code::char($tokens[$to]) === ';' ? [$property, $equals + 1] : null;
    }

    /** @param list<string> $statements */
    private function addFixture(string $property, ParsedClass $owner, array $statements, bool $isShared, bool $eager): void
    {
        [$declared] = $this->class->properties[$property];
        $type = $declared->type === null ? '' : $this->typeText($declared->type, $owner);
        unset($this->fixtures[$property], $this->defaultFixtures[$property]);
        $this->fixtures[$property] = [implode("\n", $statements), $type, $isShared, $eager];
    }

    /** Fixtures for the properties that have a default value but are not set in setUp(). */
    private function addDefaultValueFixtures(): void
    {
        foreach ($this->class->properties as $property => [$declared, $owner]) {
            if (!isset($this->fixtures[$property]) && $declared->default !== null) {
                $rewriter = $this->rewriter($owner, null);
                $value = $rewriter->rewriteFragment($declared->default);
                $this->addIssues($rewriter, "property \$$property");
                $this->addFixture($property, $owner, ["return $value;"], $declared->isStatic, eager: false);
                $this->defaultFixtures[$property] = true;
            }
        }
    }

    private function fixtureCode(): string
    {
        $code = [];
        foreach ($this->fixtures as $property => [$factory, $type, $isShared]) {
            $returnType = $type === '' ? '' : ": $type";
            $dependencies = array_map($this->parameter(...), array_values(array_filter(
                $this->usedVariables([$factory]),
                fn(string $name): bool => $name !== $property && isset($this->fixtures[$name]),
            )));
            $parameters = '(' . implode(', ', $dependencies) . ')';
            $cleanup = isset($this->cleanups[$property])
                ? ', cleanup: function (' . $this->parameter($property) . "): void {\n" . $this->indentLines($this->cleanups[$property]) . "\n}"
                : '';
            $shared = $isShared ? ', shared: true' : '';
            if (substr_count($factory, "\n") === 0 && str_starts_with($factory, 'return ')) {
                $code[] = "\$tester->fixture('$property', fn$parameters$returnType => " . substr($factory, 7, -1) . "$cleanup$shared);";
            } else {
                $code[] = "\$tester->fixture('$property', function $parameters$returnType {\n" . $this->indentLines(explode("\n", $factory)) . "\n}$cleanup$shared);";
            }
        }

        return implode("\n", $code);
    }

    /**
     * @return array{string, list<string>} the define()/defineGroup() call and what could not be translated
     */
    private function test(ParsedMethod $method, ParsedClass $owner): array
    {
        $issues = [];
        foreach (['Depends' => 'depends on another test (#[Depends]); MicroUnit tests are independent', 'RunInSeparateProcess' => 'runs in a separate process', 'PreserveGlobalState' => 'relies on global state isolation'] as $attribute => $reason) {
            if ($method->hasAttribute($attribute) || $method->annotation(lcfirst($attribute)) !== [] || ($attribute === 'RunInSeparateProcess' && $method->annotation('runInSeparateProcess') !== [])) {
                $issues[] = $reason . '.';
            }
        }

        $name = Code::testName($method->name);
        $rewriter = $this->rewriter($owner, $method, isTest: true);
        [$parameters, $renames] = $this->testParameters($method, $rewriter);
        $tokens = $this->tokensOf($this->renameVariables((string) $method->body, [...$this->localConflicts((string) $method->body), ...$renames]));
        $mocks = new MockTranslator($this->mockFixtures());
        $body = $this->testBody($tokens, $rewriter, array_map(static fn(string $parameter): string => (string) preg_replace('/^.*\$(\w+).*$/s', '$1', $parameter), $parameters), $mocks);
        $body = [...$body, ...$mocks->deferred];
        $this->useMockClasses($mocks);
        $this->flagExpectationHelpers($rewriter);
        $issues = [...$issues, ...$rewriter->issues, ...$mocks->issues];
        foreach ($rewriter->classReferences as $alias => $fullName) {
            $this->imports[$alias] ??= $fullName;
        }
        $this->functionImports += $rewriter->functionReferences;

        // Fixtures first: they are matched by name, and data parameters may have default values.
        $fixtureParameters = [];
        foreach (array_keys($rewriter->usedProperties) as $property) {
            if (isset($this->fixtures[$property])) {
                $fixtureParameters[] = $this->parameter($property);
            }
        }
        $parameters = [...$fixtureParameters, ...$parameters];
        $requirements = $this->requirements($method, $issues);
        $code = $this->indentLines([...$requirements, ...$body]);
        $returnType = $method->returnType === null ? '' : ': ' . $this->typeText($method->returnType, $owner);
        $closure = 'function (' . implode(', ', $parameters) . ")$returnType {\n$code\n}";
        $dataSets = $this->dataSets($method, $owner, $issues);

        $call = $dataSets === null
            ? "\$tester->define('$name', $closure);"
            : "\$tester->defineGroup('$name', $closure, $dataSets);";

        return [$call, $issues];
    }

    /**
     * The test's own parameters (filled by data sets); a parameter named like a fixture is renamed so
     * the fixture does not take its place.
     *
     * @return array{list<string>, array<string, string>} parameter texts and old => new variable names
     */
    private function testParameters(ParsedMethod $method, BodyRewriter $rewriter): array
    {
        if (trim($method->parameters) === '') {
            return [[], []];
        }
        $tokens = Code::tokenizeFragment($method->parameters);
        $parameters = [];
        $renames = [];
        foreach (Code::split($tokens) as [$from, $to]) {
            $parameter = self::withoutPhpUnitMockTypes(trim($rewriter->rewrite($tokens, $from, $to)));
            if (preg_match('/\$(\w+)/', $parameter, $match) && (isset($this->fixtures[$match[1]]) || isset($this->class->properties[$match[1]]))) {
                $renames[$match[1]] = $match[1] . 'Value';
                $parameter = str_replace('$' . $match[1], '$' . $match[1] . 'Value', $parameter);
            }
            $parameters[] = $parameter;
        }

        return [$parameters, $renames];
    }

    /**
     * Local variables (or parameters) named like a property the same code uses: after migration
     * both would be the same variable, so the local one is renamed.
     *
     * @return array<string, string> old => new name
     */
    private function localConflicts(string $body, string $parameters = ''): array
    {
        if (!preg_match_all('/\$this->(\w+)|(?:self|static)::\$(\w+)/', $body, $properties)) {
            return [];
        }
        $usedProperties = array_filter([...$properties[1], ...$properties[2]], fn(string $name): bool => $name !== '' && isset($this->class->properties[$name]));
        preg_match_all('/(?<![\w>:$])\$(\w+)/', $parameters . ' ' . (string) preg_replace('/\$this->\w+|(?:self|static)::\$\w+/', '', $body), $locals);
        $conflicts = [];
        foreach (array_intersect(array_unique($usedProperties), $locals[1]) as $name) {
            $conflicts[$name] = $name . 'Value';
        }

        return $conflicts;
    }

    /** @param array<string, string> $renames */
    private function renameVariables(string $body, array $renames): string
    {
        if ($renames === []) {
            return $body;
        }
        $tokens = $this->tokensOf($body);
        $out = '';
        foreach ($tokens as $index => $token) {
            $previous = Code::previous($tokens, $index);
            $isPropertyName = $previous !== null && in_array($tokens[$previous]->id, [T_OBJECT_OPERATOR, T_DOUBLE_COLON, T_NULLSAFE_OBJECT_OPERATOR], true);
            $out .= $token->id === T_VARIABLE && !$isPropertyName && isset($renames[substr($token->text, 1)])
                ? '$' . $renames[substr($token->text, 1)]
                : $token->text;
        }

        return $out;
    }

    /**
     * The test body as statements; everything after the first expectException*() call is wrapped in
     * Assert::throws(), everything after expectOutputString() is compared with the printed output.
     *
     * @param list<\PhpToken> $tokens
     * @param list<string> $parameterNames
     * @return list<string>
     */
    private function testBody(array $tokens, BodyRewriter $rewriter, array $parameterNames, MockTranslator $mocks): array
    {
        $statements = Code::statements($tokens);
        $exception = ['type' => null, 'message' => null, 'code' => null, 'regex' => null, 'object' => null];
        $output = null;
        $before = [];
        $wrapped = [];
        $isWrapping = false;
        $condition = null;

        $expect = function (string $expectation, string $argumentCode, \PhpToken $origin) use ($rewriter, &$exception, &$output, &$isWrapping): void {
            $argument = $rewriter->rewriteFragment($argumentCode);
            match (strtolower($expectation)) {
                'expectexception' => $exception['type'] = $argument,
                'expectexceptionmessage' => $exception['message'] = $argument,
                'expectexceptioncode' => $exception['code'] = $argument,
                'expectexceptionmessagematches', 'expectexceptionmessageregexp' => $exception['regex'] = $argument,
                'expectexceptionobject' => $exception['object'] = $argument,
                'expectoutputstring' => $output = ['exact', $argument],
                'expectoutputregex' => $output = ['regex', $argument],
                default => $rewriter->issue($origin, "uses \$this->$expectation(), which has no MicroUnit translation."),
            };
            $isWrapping = $isWrapping || str_starts_with(strtolower($expectation), 'expectexception');
        };

        foreach ($statements as [$from, $to]) {
            $expectations = $this->expectationsIn(trim(Code::text($tokens, $from, $to)));
            if ($expectations !== null) {
                foreach ($expectations as [$expectation, $argumentCode]) {
                    $expect($expectation, $argumentCode, $tokens[$from]);
                }
                continue;
            }
            // `if (condition) { $this->expectException(...); }`: the exception is expected for some data sets only.
            $conditional = $isWrapping ? null : $this->conditionalExpectations($tokens, $from, $to);
            if ($conditional !== null) {
                [$conditionFrom, $conditionTo, $expectations] = $conditional;
                $condition = trim($rewriter->rewrite($tokens, $conditionFrom, $conditionTo));
                foreach ($expectations as [$expectation, $argumentCode]) {
                    $expect($expectation, $argumentCode, $tokens[$from]);
                }
                continue;
            }
            $code = $mocks->translate(Code::reindent(trim($rewriter->rewrite($tokens, $from, $to)), self::baseIndentation($tokens)));
            if ($code === '') {
                continue;
            }
            if ($isWrapping) {
                $wrapped[] = [$code, $from, $to];
            } else {
                $before[] = $code;
            }
        }

        if ($output !== null) {
            $before = ['ob_start();', ...$before];
        }
        if ($isWrapping) {
            $before = [...$before, ...$this->throwsCode($exception, $wrapped, $tokens, $statements, $parameterNames, array_keys($rewriter->usedProperties), $condition)];
        }
        if ($output !== null) {
            $before[] = $output[0] === 'exact'
                ? "Assert::exact({$output[1]}, (string) ob_get_clean());"
                : "Assert::matchesRegex({$output[1]}, (string) ob_get_clean());";
        }

        return $before;
    }

    /**
     * @param array{type: ?string, message: ?string, code: ?string, regex: ?string, object: ?string} $exception
     * @param list<array{string, int, int}> $wrapped
     * @param list<\PhpToken> $tokens
     * @param list<array{int, int}> $statements
     * @param list<string> $parameterNames
     * @param list<string> $properties
     * @return list<string>
     */
    private function throwsCode(array $exception, array $wrapped, array $tokens, array $statements, array $parameterNames, array $properties, ?string $condition = null): array
    {
        $prelude = [];
        if ($exception['object'] !== null) {
            $prelude[] = "\$expectedException = {$exception['object']};";
            $exception['code'] ??= '$expectedException->getCode()';
        }
        $type = $exception['type'] ?? ($exception['object'] === null ? null : '$expectedException::class');
        $message = $exception['message'] ?? ($exception['object'] === null ? null : '$expectedException->getMessage()');
        $arguments = $type ?? 'null';
        if ($message !== null) {
            $arguments .= ", $message";
        }
        $codes = array_column($wrapped, 0);

        if (count($codes) === 1 && preg_match('/;$/', $codes[0]) && !preg_match('/^(return|if|foreach|for|while|try|switch|echo|unset)\b/', $codes[0]) && !str_contains($codes[0], "\n")) {
            $callable = 'fn() => ' . rtrim($codes[0], ';');
        } else {
            $wrappedVariables = $this->variablesIn(implode("\n", $codes));
            $earlierVariables = [...$parameterNames, ...$properties];
            foreach ($statements as [$from, $to]) {
                if ($wrapped !== [] && $from >= $wrapped[0][1]) {
                    break;
                }
                $earlierVariables = [...$earlierVariables, ...$this->variablesIn(Code::text($tokens, $from, $to))];
            }
            $uses = array_values(array_intersect(array_unique($earlierVariables), $wrappedVariables));
            $useList = $uses === [] ? '' : ' use (' . implode(', ', array_map(static fn(string $name): string => '$' . $name, $uses)) . ')';
            $callable = "function ()$useList {\n" . $this->indentLines($codes) . "\n}";
        }

        $checksCode = $exception['code'] !== null || $exception['regex'] !== null;
        $assertions = [($checksCode ? '$exception = ' : '') . 'Assert::throws(' . ($condition === null ? $callable : '$run') . ", $arguments);"];
        if ($exception['code'] !== null) {
            $assertions[] = "Assert::equals({$exception['code']}, \$exception->getCode());";
        }
        if ($exception['regex'] !== null) {
            $assertions[] = "Assert::matchesRegex({$exception['regex']}, \$exception->getMessage());";
        }
        if ($condition === null) {
            return [...$prelude, ...$assertions];
        }

        return ["\$run = $callable;", "if ($condition) {\n" . $this->indentLines([...$prelude, ...$assertions]) . "\n} else {\n    \$run();\n}"];
    }

    /**
     * For `if (condition) { <only expectations> }` without else: the condition's token range and
     * the expectations. Null for any other statement.
     *
     * @param list<\PhpToken> $tokens
     * @return array{int, int, list<array{string, string}>}|null
     */
    private function conditionalExpectations(array $tokens, int $from, int $to): ?array
    {
        $if = $tokens[$from]->isIgnorable() ? Code::next($tokens, $from) : $from;
        if ($if === null || $tokens[$if]->id !== T_IF) {
            return null;
        }
        $conditionOpen = (int) Code::next($tokens, $if);
        $conditionClose = Code::closing($tokens, $conditionOpen);
        $blockOpen = Code::next($tokens, $conditionClose);
        if ($blockOpen === null || Code::char($tokens[$blockOpen]) !== '{' || Code::closing($tokens, $blockOpen) !== $to) {
            return null;
        }

        $block = array_slice($tokens, $blockOpen, $to - $blockOpen + 1);
        $expectations = [];
        foreach (Code::statements($block) as [$statementFrom, $statementTo]) {
            $found = $this->expectationsIn(trim(Code::text($block, $statementFrom, $statementTo)));
            if ($found === null) {
                return null;
            }
            $expectations = [...$expectations, ...$found];
        }

        return $expectations === [] ? null : [$conditionOpen + 1, $conditionClose - 1, $expectations];
    }

    /**
     * The expectations a statement sets: a direct $this->expect*() call, or a call to an
     * expectation helper with its arguments filled in. Null for any other statement.
     *
     * @return list<array{string, string}>|null [expect method, argument code]
     */
    private function expectationsIn(string $statement): ?array
    {
        if (!preg_match('/^(?:\$this->|self::|static::)(\w+)\s*\((.*)\)\s*;$/s', $statement, $match)) {
            return null;
        }
        $name = $match[1];
        if (preg_match('/^(expectException\w*|expectOutputString|expectOutputRegex)$/i', $name) && $this->class->method($name) === null) {
            $argumentTokens = Code::tokenizeFragment($match[2]);
            $first = Code::split($argumentTokens)[0] ?? null;

            return [[$name, $first === null ? '' : trim(Code::text($argumentTokens, $first[0], $first[1]))]];
        }
        $helper = $this->expectationHelpers[strtolower($name)] ?? null;
        if ($helper === null) {
            return null;
        }

        $argumentTokens = Code::tokenizeFragment($match[2]);
        $values = [];
        foreach (Code::split($argumentTokens) as $index => [$from, $to]) {
            $value = trim(Code::text($argumentTokens, $from, $to));
            $isSimple = count(array_filter(array_slice($argumentTokens, $from, $to - $from + 1), static fn(\PhpToken $token): bool => !$token->isIgnorable())) === 1;
            $values[$helper[0][$index] ?? "argument$index"] = $isSimple ? $value : "($value)";
        }
        $expectations = [];
        foreach ($helper[1] as [$expectation, $argumentCode]) {
            $tokens = Code::tokenizeFragment($argumentCode);
            $filled = '';
            foreach ($tokens as $token) {
                $filled .= $token->id === T_VARIABLE && isset($values[substr($token->text, 1)]) ? $values[substr($token->text, 1)] : $token->text;
            }
            $expectations[] = [$expectation, $filled];
        }

        return $expectations;
    }

    /** @param array<string, array{ParsedMethod, ParsedClass}> $functions */
    private function findExpectationHelpers(array $functions): void
    {
        foreach ($functions as $lower => [$method]) {
            $tokens = $this->tokensOf((string) $method->body);
            $expectations = [];
            foreach (Code::statements($tokens) as [$from, $to]) {
                if (!preg_match('/^\$this->(expect\w+)\s*\((.*)\)\s*;$/s', trim(Code::text($tokens, $from, $to)), $match)) {
                    continue 2;
                }
                $expectations[] = [$match[1], $match[2]];
            }
            if ($expectations !== []) {
                preg_match_all('/\$(\w+)/', $method->parameters, $parameters);
                $this->expectationHelpers[$lower] = [$parameters[1], $expectations];
            }
        }
    }

    /**
     * Expectation helpers are only expanded at the top level of a test; a call anywhere else
     * would call a function that does not exist.
     */
    private function flagExpectationHelpers(BodyRewriter $rewriter): void
    {
        foreach (array_keys(array_intersect_key($rewriter->calledHelpers, $this->expectationHelpers)) as $helper) {
            $name = $this->class->method($helper)?->name ?? $helper;
            $rewriter->issues[] = "calls $name(), which only sets expectations; migrate can only expand that at the top level of a test.";
        }
    }

    /**
     * #[Requires*] of the class and the test, and @requires annotations, as skipIf() calls.
     *
     * @param list<string> $issues
     * @return list<string>
     */
    private function requirements(ParsedMethod $method, array &$issues): array
    {
        $requirements = [];
        foreach ([...$this->class->class->attributes, ...$method->attributes] as ['name' => $attribute, 'arguments' => $argument]) {
            if (str_starts_with(strtolower($attribute), 'requires')) {
                $requirements[] = [$attribute, (string) $argument, "#[$attribute($argument)]"];
            }
        }
        $annotations = ['php' => 'RequiresPhp', 'extension' => 'RequiresPhpExtension', 'function' => 'RequiresFunction', 'osfamily' => 'RequiresOperatingSystemFamily', 'os' => 'RequiresOperatingSystem'];
        foreach ($method->annotation('requires') as $annotation) {
            [$kind, $value] = preg_split('/\s+/', $annotation, 2) + [1 => ''];
            if (strcasecmp($kind, 'PHPUnit') !== 0) {
                $requirements[] = [$annotations[strtolower($kind)] ?? '', var_export(trim($value), true), "@requires $annotation"];
            }
        }

        $lines = [];
        foreach ($requirements as [$attribute, $argument, $written]) {
            $line = self::requirement($attribute, $argument);
            if ($line === null) {
                $issues[] = "has $written, which migrate cannot translate.";
            } else {
                $lines[] = $line;
            }
        }

        return $lines;
    }

    /** One requirement as an Assert::skipIf() call, null when it has no translation. */
    private static function requirement(string $attribute, string $argument): ?string
    {
        $arguments = Code::split(Code::tokenizeFragment($argument));
        $argument = trim($argument);

        return match (strtolower($attribute)) {
            'requiresphp' => preg_match('/^[\'"]\s*(<=|>=|<|>|==|=|!=)?\s*([\d.]+)\s*[\'"]$/', $argument, $match) === 1
                ? self::phpRequirement($match[2], $match[1] === '' ? '>=' : $match[1])
                : null,
            'requiresphpextension' => count($arguments) === 1 ? "Assert::skipIf(!extension_loaded($argument), 'Requires the ' . $argument . ' extension.');" : null,
            'requiresoperatingsystemfamily' => "Assert::skipIf(PHP_OS_FAMILY !== $argument, 'Requires ' . $argument . '.');",
            'requiresoperatingsystem' => "Assert::skipIf(preg_match('/' . $argument . '/i', PHP_OS) !== 1, 'Requires the operating system ' . $argument . '.');",
            'requiresfunction' => "Assert::skipIf(!function_exists($argument), 'Requires ' . $argument . '().');",
            default => null,
        };
    }

    private static function phpRequirement(string $version, string $operator): string
    {
        return "Assert::skipIf(!version_compare(PHP_VERSION, '$version', '$operator'), 'Requires PHP $operator $version.');";
    }

    /**
     * The data sets of a parameterized test as code, or null for a plain test.
     *
     * @param list<string> $issues
     */
    private function dataSets(ParsedMethod $method, ParsedClass $owner, array &$issues): ?string
    {
        $sources = [];
        // Like PHPUnit 10+: docblock annotations only count when the method has no attributes for it.
        $attributeProviders = array_map(static fn(string $argument): string => trim($argument, " '\""), $method->attributeArguments('DataProvider'));
        $hasProviderAttributes = $attributeProviders !== [] || $method->attributeArguments('DataProviderExternal') !== [];
        $providers = array_values(array_unique($hasProviderAttributes ? $attributeProviders : $method->annotation('dataProvider')));
        foreach ($providers as $provider) {
            if ($this->class->method($provider) === null) {
                $issues[] = "uses the data provider $provider(), which was not found.";
                continue;
            }
            $sources[] = $this->helperCallCode($provider);
        }
        foreach ($method->attributeArguments('DataProviderExternal') as $argument) {
            [$class, $provider] = array_map(static fn(string $part): string => trim((string) preg_replace('/^\s*\w+\s*:(?!:)/', '', $part)), explode(',', $argument) + [1 => '']);
            $rewriter = $this->rewriter($owner, $method);
            $sources[] = $rewriter->rewriteFragment(preg_replace('/::class$/i', '', $class) . '::' . trim($provider, '\'"') . '()');
            $this->addIssues($rewriter, "#[DataProviderExternal] of $method->name()");
        }

        $inline = [];
        foreach ($method->attributeArguments('TestWith') as $argument) {
            $tokens = Code::tokenizeFragment($argument);
            $rewriter = $this->rewriter($owner, $method);
            $parts = array_map(fn(array $range): string => trim($rewriter->rewrite($tokens, $range[0], $range[1])), Code::split($tokens));
            $this->addIssues($rewriter, "#[TestWith] of $method->name()");
            $inline[] = isset($parts[1]) ? preg_replace('/^name:\s*/', '', $parts[1]) . " => {$parts[0]}" : $parts[0];
        }
        foreach ($method->attributeArguments('TestWithJson') as $argument) {
            $inline[] = "json_decode($argument, true)";
        }
        foreach ($method->attributeArguments('TestWith') === [] && $method->attributeArguments('TestWithJson') === [] ? $method->testWithDataSets() : [] as $json) {
            $inline[] = 'json_decode(' . var_export($json, true) . ', true)';
        }
        if ($inline !== []) {
            $sources[] = "[\n" . $this->indentLines(array_map(static fn(string $set): string => "$set,", $inline)) . "\n]";
        }

        return match (count($sources)) {
            0 => null,
            1 => $sources[0],
            default => '[' . implode(', ', array_map(static fn(string $source): string => "...$source", $sources)) . ']',
        };
    }

    /** A helper method as a function of the file's namespace; the properties it uses come first. */
    private function function(string $lower, ParsedMethod $method, ParsedClass $owner): string
    {
        $level = self::levelOf($lower);
        $rewriter = $this->rewriter($owner, $method, null, $level);
        $conflicts = $this->localConflicts((string) $method->body, $method->parameters);
        $tokens = $this->tokensOf($this->renameVariables((string) $method->body, $conflicts));
        $issueCount = count($this->issues);
        $inner = $this->mocks($rewriter->rewrite($tokens, 1, count($tokens) - 2), "$method->name()");
        $parameterCode = $conflicts === [] ? $method->parameters : (string) preg_replace_callback('/\$(\w+)\b/', static fn(array $m): string => '$' . ($conflicts[$m[1]] ?? $m[1]), $method->parameters);
        $parameters = self::withoutPhpUnitMockTypes($rewriter->rewriteFragment($parameterCode));
        $this->flagExpectationHelpers($rewriter);
        $hasHardIssue = $rewriter->issues !== [] || str_contains($inner, MockTranslator::MARKER);
        $this->addIssues($rewriter, "$method->name()");
        if ($hasHardIssue) {
            $reasons = implode(' ', array_slice($this->issues, $issueCount));
            $inner = "\n    throw new \\LogicException('" . addslashes("Needs manual migration: $reasons") . "');\n\n" . self::originalCode($method) . "\n";
        }

        $state = array_map(fn(array $entry): string => $this->parameter($entry[0], byReference: $entry[1]), $this->state[$lower] ?? []);
        $allParameters = implode(', ', array_filter([...$state, $parameters], static fn(string $parameter): bool => $parameter !== ''));
        $returnType = $method->returnType === null ? '' : ': ' . $this->typeText($method->returnType, $owner);
        $doc = $method->docComment === null ? '' : Code::reindent($method->docComment, Code::indentation($method->docComment)) . "\n";
        $body = Code::reindent($inner, Code::indentation($inner) - 4);

        $name = $level === 0 ? $method->name : Code::parentFunctionName($method->name, $level);

        return $doc . 'function ' . $name . "($allParameters)$returnType\n{" . rtrim($body) . "\n}";
    }

    /** A test that needs manual migration: skipped, with the reasons and the original code. @param list<string> $issues */
    private function skippedTest(string $name, ParsedMethod $method, array $issues): string
    {
        $reason = addslashes('Needs manual migration: ' . implode(' ', $issues));

        return "\$tester->define('$name', function (): void {\n    Assert::skip('$reason');\n\n" . self::originalCode($method) . "\n});";
    }

    /** The method's original body as indented comment lines, kept for manual migration. */
    private static function originalCode(ParsedMethod $method): string
    {
        $original = Code::reindent((string) $method->body, Code::indentation((string) $method->body));

        return "    // Original PHPUnit code:\n" . implode("\n", array_map(static fn(string $line): string => '    // ' . $line, explode("\n", trim($original))));
    }

    private function header(): string
    {
        $class = $this->class->class;
        $namespace = ($class->namespace === null ? 'Migrated' : $class->namespace) . '\\' . $class->name;
        // One class may be imported under several aliases (a parent and a child class can differ).
        $uses = ['assert' => ['MicroUnit\Assertion\Assert', 'Assert'], 'testsetup' => ['MicroUnit\Setup\TestSetup', 'TestSetup']];
        foreach ($this->imports as $alias => $fullName) {
            $isTakenByMicroUnit = isset(self::RESERVED_NAMES[strtolower($alias)]) && strcasecmp(self::RESERVED_NAMES[strtolower($alias)], $fullName) !== 0;
            if (!$isTakenByMicroUnit) {
                $uses[strtolower($alias)] ??= [$fullName, $alias];
            }
        }
        $useLines = [];
        foreach ($uses as [$fullName, $alias]) {
            $short = substr($fullName, (int) strrpos('\\' . $fullName, '\\'));
            $useLines[] = 'use ' . $fullName . (strcasecmp($short, $alias) === 0 ? '' : " as $alias") . ';';
        }
        foreach ($class->uses as $statement) {
            if (preg_match('/^use\s+(function|const)\s/i', $statement)) {
                $useLines[] = $statement . ';';
            }
        }
        foreach ($this->functionImports as $fullName) {
            $useLines[] = "use function $fullName;";
        }
        $group = static fn(string $line): int => str_starts_with($line, 'use function ') ? 1 : (str_starts_with($line, 'use const ') ? 2 : 0);
        usort($useLines, static fn(string $a, string $b): int => [$group($a), $a] <=> [$group($b), $b]);

        return "<?php\n\n" . ($class->hasStrictTypes ? "declare(strict_types=1);\n\n" : '')
            . '// Migrated from ' . basename($class->file) . " by `microunit migrate`.\n\n"
            . "namespace $namespace;\n\n" . implode("\n", array_unique($useLines))
            . ($this->context->supportFile === null ? '' : "\n\n// Classes and functions the original test file declared next to the test.\nrequire_once __DIR__ . '/{$this->context->supportFile}';");
    }

    private function constantCode(): string
    {
        $lines = [];
        foreach ($this->class->constants as $name => [$value, $owner]) {
            $rewriter = $this->rewriter($owner, null);
            $lines[] = "const $name = " . $rewriter->rewriteFragment($value) . ';';
            $this->addIssues($rewriter, "constant $name");
        }

        return implode("\n", $lines);
    }

    /** Framework traits (RefreshDatabase, ...) stay on the hosted test case. */
    private function traitUses(): string
    {
        $lines = [];
        $frameworkTraits = [];
        foreach ($this->class->frameworkTraits as $trait) {
            if (in_array(strtolower($trait), self::MOCKERY_TRAITS, true)) {
                continue;
            }
            if ($this->class->proxy === null) {
                $this->issues[] = "uses the trait $trait, which is not among the migrated files.";
                continue;
            }
            $short = substr($trait, (int) strrpos('\\' . $trait, '\\'));
            $this->imports[$short] = $trait;
            $frameworkTraits[] = "$short::class";
        }
        if ($frameworkTraits !== []) {
            $proxy = (string) $this->class->proxy;
            $short = substr($proxy, (int) strrpos('\\' . $proxy, '\\'));
            $this->imports[$short] = $proxy;
            $lines[] = "$short::uses(\$tester, " . implode(', ', $frameworkTraits) . ');';
        }

        return implode("\n", $lines);
    }

    /**
     * Translates PHPUnit mocks in code outside a test. Expectations from setUp() are checked after
     * every test; anywhere else they are reported.
     */
    private function mocks(string $code, string $where, bool $isSetUp = false): string
    {
        $mocks = new MockTranslator($this->mockFixtures());
        $code = $mocks->translate($code);
        $this->useMockClasses($mocks);
        foreach ($mocks->issues as $issue) {
            $this->issues[] = "$where $issue";
        }
        if ($isSetUp) {
            $this->setUpChecks = [...$this->setUpChecks, ...$mocks->deferred];
        } elseif ($mocks->deferred !== []) {
            $this->issues[] = "$where sets mock expectations outside a test or setUp(); they are not checked after migration.";
        }

        return $code;
    }

    private function useMockClasses(MockTranslator $mocks): void
    {
        if ($mocks->usesBuilder) {
            $this->imports['MockBuilder'] = 'MicroUnit\Mock\MockBuilder';
        }
        if ($mocks->usesMicroMock) {
            $this->imports['MicroMock'] = 'MicroUnit\Mock\MicroMock';
        }
        if ($mocks->usesAssertMock || $mocks->deferred !== []) {
            $this->imports['AssertMock'] = 'MicroUnit\Assertion\AssertMock';
        }
    }

    /** @return list<string> the fixtures that are PHPUnit mocks, as variables */
    private function mockFixtures(): array
    {
        $mocks = array_filter($this->fixtures, static fn(array $fixture): bool => str_contains($fixture[0], 'MockBuilder::create('));

        return array_map(static fn(string $name): string => "\$$name", array_keys($mocks));
    }

    /** 0 for a class's own helper ("wrap"), n for its n-th parent's version ("wrap#1"). */
    private static function levelOf(string $functionKey): int
    {
        return (int) (explode('#', $functionKey)[1] ?? 0);
    }

    private function rewriter(ParsedClass $owner, ?ParsedMethod $method, ?\Closure $stateOf = null, int $level = 0, bool $isTest = false): BodyRewriter
    {
        [$names, $renames] = $this->ownerNames[spl_object_id($owner)] ??= $this->namesOf($owner);

        return new BodyRewriter(
            $this->class,
            $names,
            $stateOf ?? fn(string $helper): array => $this->state[strtolower($helper)] ?? [],
            $method?->bodyLine ?? $owner->line,
            $this->context->directoryOf($owner->file),
            $owner->file,
            $this->context->functions,
            $renames,
            $method?->name,
            $this->import(...),
            $level,
            $isTest,
        );
    }

    /** @return array{Names, array<string, string>} the owner's names and the aliases that need another name (MicroUnit uses them) */
    private function namesOf(ParsedClass $owner): array
    {
        $names = Names::of($owner->namespace, $owner->uses);
        $renames = [];
        foreach ($names->imports() as $alias => $fullName) {
            if (isset(self::RESERVED_NAMES[$alias]) && strcasecmp(self::RESERVED_NAMES[$alias], $fullName) !== 0 && !str_starts_with(strtolower($fullName), 'phpunit\\')) {
                $renames[$alias] = explode('\\', $fullName)[0] . substr($fullName, (int) strrpos($fullName, '\\') + 1);
            }
        }

        return [$names, $renames];
    }

    /** Claims $alias for $fullName in the migrated file; false when the alias already means another class. */
    private function import(string $alias, string $fullName): bool
    {
        foreach ($this->imports as $existing => $existingFullName) {
            if (strcasecmp($existing, $alias) === 0) {
                return strcasecmp($existingFullName, $fullName) === 0;
            }
        }
        $reserved = self::RESERVED_NAMES[strtolower($alias)] ?? null;
        if ($reserved !== null && strcasecmp($reserved, $fullName) !== 0) {
            return false;
        }
        $this->imports[$alias] = $fullName;

        return true;
    }

    private function addIssues(BodyRewriter $rewriter, string $where): void
    {
        foreach ($rewriter->issues as $issue) {
            $this->issues[] = "$where $issue";
        }
        $rewriter->issues = [];
        foreach ($rewriter->classReferences as $alias => $fullName) {
            $this->imports[$alias] ??= $fullName;
        }
        $this->functionImports += $rewriter->functionReferences;
    }

    private function helperCallCode(string $helper): string
    {
        $method = $this->class->method($helper);
        $state = array_map(static fn(array $entry): string => '$' . $entry[0], $this->state[strtolower($helper)] ?? []);

        return ($method?->name ?? $helper) . '(' . implode(', ', $state) . ')';
    }

    private function parameter(string $property, bool $byReference = false): string
    {
        $type = $this->fixtures[$property][1] ?? null;
        if ($type === null) {
            [$declared, $owner] = $this->class->properties[$property];
            $type = $declared->type === null ? '' : $this->typeText($declared->type, $owner);
        }

        // A helper that assigns the property may run before anything else did: the variable is still null.
        if ($byReference) {
            $type = self::nullable($type);
        }

        return ($type === '' ? '' : "$type ") . ($byReference ? '&' : '') . '$' . $property;
    }

    /** "Mailer" => "?Mailer", "A|B" => "A|B|null", "A&B" => "(A&B)|null"; already nullable types stay. */
    private static function nullable(string $type): string
    {
        return match (true) {
            $type === '' || $type === 'mixed' || $type === 'null' || str_starts_with($type, '?') || preg_match('/(^|\|)\s*null\s*($|\|)/i', $type) === 1 => $type,
            str_contains($type, '&') && !str_contains($type, '|') => "($type)|null",
            str_contains($type, '|') => "$type|null",
            default => "?$type",
        };
    }

    /** A type declaration with its class names imported. */
    private function typeText(string $type, ParsedClass $owner): string
    {
        $key = spl_object_id($owner) . ' ' . $type;
        if (!isset($this->typeTexts[$key])) {
            $rewriter = $this->rewriter($owner, null);
            $text = $rewriter->rewriteFragment($type . ' $x');
            $this->addIssues($rewriter, 'type');
            $this->typeTexts[$key] = self::withoutPhpUnitMockTypes(trim(substr($text, 0, -2)));
        }

        return $this->typeTexts[$key];
    }

    /** "Mailer&MockObject" => "Mailer": MicroUnit mocks extend the mocked type, not PHPUnit's mock interfaces. */
    private static function withoutPhpUnitMockTypes(string $code): string
    {
        $mockType = '\\\\?(?:PHPUnit\\\\Framework\\\\MockObject\\\\)?(?:MockObject|Stub)\b';

        return (string) preg_replace(["/\\s*&\\s*$mockType/", "/$mockType\\s*&\\s*/"], '', $code);
    }

    /** Whether evaluating the expression has no side effects worth preserving (so its fixture may be lazy). @param list<\PhpToken> $tokens */
    private function isPure(array $tokens, int $from, int $to): bool
    {
        for ($i = $from; $i <= $to; $i++) {
            $next = Code::next($tokens, $i);
            $isCall = $next !== null && Code::char($tokens[$next]) === '(';
            $previous = Code::previous($tokens, $i);
            $isConstruction = $previous !== null && $tokens[$previous]->id === T_NEW;
            if ($isCall && $tokens[$i]->id === T_STRING && !$isConstruction && !in_array(strtolower($tokens[$i]->text), ['createmock', 'createstub', 'mock', 'array_fill', 'range', 'sprintf', 'dirname', 'basename', 'realpath', 'str_repeat'], true)) {
                return false;
            }
        }

        return true;
    }

    /** @param list<string> $code @return list<string> variable names (without $) used in the code */
    private function usedVariables(array $code): array
    {
        return $this->variablesIn(implode("\n", $code));
    }

    /** @return list<string> */
    private function variablesIn(string $code): array
    {
        // $variable, or a variable method name: Class::$method(...); but not a static property Class::$property.
        preg_match_all('/(?<![\w>:$])\$(\w+)|::\$(\w+)(?=\s*\()/', $code, $matches);
        $names = array_filter([...$matches[1], ...$matches[2]], static fn(string $name): bool => $name !== '');

        return array_values(array_diff(array_unique($names), ['this', 'GLOBALS', '_SERVER', '_GET', '_POST', '_FILES', '_COOKIE', '_SESSION', '_REQUEST', '_ENV']));
    }

    /** @param list<string> $lines */
    private function indentLines(array $lines): string
    {
        $code = implode("\n", $lines);

        return Code::reindent('    ' . $code, -4) === '' ? '' : '    ' . Code::reindent($code, -4);
    }

    /** @return list<\PhpToken> */
    private function tokensOf(string $body): array
    {
        return $this->bodyTokens[$body] ??= Code::tokenizeBody($body);
    }

    /**
     * The earlier statements that assign the given variables, and the ones those depend on, in order.
     *
     * @param list<string> $statements
     * @param list<string> $variables
     * @return array<int, string> statement index => code
     */
    private static function statementsAssigning(array $statements, array $variables): array
    {
        $needed = array_fill_keys($variables, true);
        $picked = [];
        for ($index = count($statements) - 1; $index >= 0 && $needed !== []; $index--) {
            $assigned = self::assignedVariables($statements[$index]);
            if (array_intersect($assigned, array_keys($needed)) === []) {
                continue;
            }
            $picked[$index] = $statements[$index];
            preg_match_all('/(?<![\w>:$])\$(\w+)/', $statements[$index], $used);
            foreach ($used[1] as $variable) {
                if (!in_array($variable, $assigned, true) && $variable !== 'this') {
                    $needed[$variable] = true;
                }
            }
        }
        ksort($picked);

        return $picked;
    }

    /** @return list<string> the variables a statement assigns (`$x = `, `$x[] = `) */
    private static function assignedVariables(string $code): array
    {
        preg_match_all('/\$(\w+)\s*(?:\[[^\]]*\]\s*)*=(?![=>])/', $code, $assigned);

        return $assigned[1];
    }

    /** Indentation of the statements inside a "{ ... }" body. @param list<\PhpToken> $tokens */
    private static function baseIndentation(array $tokens): int
    {
        return Code::indentation(Code::text($tokens, 1, count($tokens) - 2));
    }
}
