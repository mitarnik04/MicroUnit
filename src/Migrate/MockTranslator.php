<?php

namespace MicroUnit\Migrate;

/**
 * Translates PHPUnit's mock API into MicroUnit mocks. BodyRewriter leaves PHPUnit mock calls as
 * `__PHPUNIT_MOCK__::name(...)` markers; this class turns
 *
 * - creation (createMock, createStub, createPartialMock, createConfiguredMock, getMockBuilder()->...->getMock())
 *   into MockBuilder::create(...)->build()->newInstance();
 * - stubbing ($mock->method('x')->willReturn(...)) into MicroMock::of($mock)->returns('x', ...);
 * - expectations ($mock->expects($this->once())->method('x')->with(...)) into AssertMock checks that
 *   run when the test ends, which is when PHPUnit verifies them too.
 *
 * Whatever is left as a marker could not be translated and is reported.
 */
final class MockTranslator
{
    public const string MARKER = '__PHPUNIT_MOCK__';

    /** Builder methods without an effect in MicroUnit. */
    private const array IGNORED_BUILDER_CALLS = [
        'disableoriginalclone', 'enableoriginalclone', 'disableargumentcloning', 'enableargumentcloning', 'disallowmockingunknowntypes',
        'allowmockingunknowntypes', 'disableautoload', 'enableautoload', 'setmockclassname', 'enableautoreturnvaluegeneration', 'disableautoreturnvaluegeneration',
    ];

    /** Calls of a stubbing / expectation chain ($mock->expects(...)->method(...)->with(...)->willReturn(...)). */
    private const array MOCK_CALLS = [
        'method', 'expects', 'with', 'withanyparameters', 'will', 'willreturn', 'willreturnonconsecutivecalls', 'willreturncallback',
        'willthrowexception', 'willreturnself', 'willreturnargument', 'willreturnmap', 'willreturnreference',
    ];

    /** @var list<string> AssertMock checks to run at the end of the test */
    public array $deferred = [];

    /** @var list<string> */
    public array $issues = [];

    /** @var bool whether the code now uses MockBuilder / MicroMock / AssertMock */
    public bool $usesBuilder = false;

    public bool $usesMicroMock = false;

    public bool $usesAssertMock = false;

    /** @var array<string, true> variables (with $) that hold PHPUnit mocks */
    private array $mockVariables;

    /** @var array<string, true> lowercase mock API calls whose problem was already reported */
    private array $explained = [];

    /** @param list<string> $mockVariables variables (with $) known to hold PHPUnit mocks, e.g. fixtures created with createMock() */
    public function __construct(array $mockVariables = [])
    {
        $this->mockVariables = array_fill_keys($mockVariables, true);
    }

    public function translate(string $code): string
    {
        if (!str_contains($code, self::MARKER) && !preg_match('/->\s*(method|expects)\s*\(/', $code)) {
            return $code;
        }
        if (preg_match_all('/(\$\w+)\s*=\s*' . self::MARKER . '::(?:create|getMock)/i', $code, $created) > 0) {
            $this->mockVariables += array_fill_keys($created[1], true);
        }
        $tokens = Code::tokenizeFragment($code);
        $out = '';
        for ($i = 0, $count = count($tokens); $i < $count; $i++) {
            if ($this->isChainStart($tokens, $i)) {
                [$text, $i] = $this->chain($tokens, $i);
                $out .= $text;
                continue;
            }
            if ($tokens[$i]->text === self::MARKER) {
                [$text, $i] = $this->marker($tokens, $i);
                $out .= $text;
                continue;
            }
            $out .= $tokens[$i]->text;
        }
        if (str_contains($out, self::MARKER)) {
            preg_match_all('/' . self::MARKER . '::(\w+)/', $out, $leftovers);
            foreach (array_unique($leftovers[1]) as $name) {
                if (isset($this->explained[strtolower($name)])) {
                    continue;
                }
                $this->issues[] = "uses PHPUnit's mock API (\$this->$name()) in a way migrate cannot translate; rewrite it with MockBuilder or Mockery.";
            }
        }

        return $out;
    }

    /** `$mock->method(` or `$mock->expects(` at the start of a statement. @param list<\PhpToken> $tokens */
    private function isChainStart(array $tokens, int $i): bool
    {
        if ($tokens[$i]->id !== T_VARIABLE) {
            return false;
        }
        $previous = Code::previous($tokens, $i);
        if ($previous !== null && !in_array(Code::char($tokens[$previous]), [';', '{', '}'], true)) {
            return false;
        }
        $arrow = Code::next($tokens, $i);
        $name = $arrow === null ? null : Code::next($tokens, $arrow);

        if ($name === null || $tokens[(int) $arrow]->id !== T_OBJECT_OPERATOR || !in_array(strtolower($tokens[$name]->text), ['method', 'expects'], true)) {
            return false;
        }
        // Mockery's $mock->expects()->method(...) shorthand takes no argument; it keeps working unchanged.
        $open = Code::next($tokens, $name);
        $argument = $open === null ? null : Code::next($tokens, $open);

        return strtolower($tokens[$name]->text) === 'method' || ($argument !== null && Code::char($tokens[$argument]) !== ')');
    }

    /**
     * A stubbing / expectation statement up to its ";".
     *
     * @param list<\PhpToken> $tokens
     * @return array{string, int}
     */
    private function chain(array $tokens, int $i): array
    {
        $variable = $tokens[$i]->text;
        $calls = [];
        $cursor = $i;
        while (true) {
            $arrow = Code::next($tokens, $cursor);
            if ($arrow === null || $tokens[$arrow]->id !== T_OBJECT_OPERATOR) {
                break;
            }
            $nameIndex = (int) Code::next($tokens, $arrow);
            $open = (int) Code::next($tokens, $nameIndex);
            if (Code::char($tokens[$open]) !== '(') {
                return [Code::text($tokens, $i, $cursor), $cursor];
            }
            $close = Code::closing($tokens, $open);
            $arguments = array_map(static fn(array $range): string => trim(Code::text($tokens, $range[0], $range[1])), Code::arguments($tokens, $open, $close));
            $calls[] = [strtolower($tokens[$nameIndex]->text), $arguments];
            $cursor = $close;
        }
        $end = Code::next($tokens, $cursor);
        $hasSemicolon = $end !== null && Code::char($tokens[$end]) === ';';
        $last = $hasSemicolon ? (int) $end : $cursor;

        // `$route->method('POST')` on an object that is not a mock is an ordinary call.
        $isMock = isset($this->mockVariables[$variable]) || array_any($calls, static fn(array $call): bool => $call[0] !== 'method' && in_array($call[0], self::MOCK_CALLS, true));
        if (!$isMock) {
            return [Code::text($tokens, $i, $last), $last];
        }

        $method = null;
        $count = null;
        $with = null;
        $plan = null;
        foreach ($calls as [$name, $arguments]) {
            $first = $arguments[0] ?? '';
            match ($name) {
                'method' => $method = $first,
                'expects' => $count = $this->countCheck($first),
                'with' => $with = $arguments,
                'withanyparameters' => null,
                'willreturn' => $plan = count($arguments) > 1 ? ['returnsSequence', implode(', ', $arguments)] : ['returns', $first],
                'willreturnonconsecutivecalls' => $plan = ['returnsSequence', implode(', ', $arguments)],
                'willreturncallback' => $plan = ['returnsCallback', $first],
                'willthrowexception' => $plan = ['throws', $first],
                'willreturnself' => $plan = ['returnsCallback', "fn() => $variable"],
                'willreturnargument' => $plan = ['returnsCallback', "fn(mixed ...\$arguments) => \$arguments[$first]"],
                'willreturnmap' => $plan = ['returnsMap', $first],
                'willreturnreference' => $plan = ['returns', $first],
                'will' => $plan = $this->willPlan($first, $variable),
                default => $this->issues[] = "uses ->$name() on a PHPUnit mock, which migrate cannot translate.",
            };
        }
        if ($method === null || preg_match('/^[\'"]\w+[\'"]$/', $method) !== 1) {
            $this->issues[] = 'configures a PHPUnit mock method that is not a plain name; rewrite it with MicroMock::of().';

            return [Code::text($tokens, $i, $last), $last];
        }

        $checks = [];
        if ($count !== null && $count !== '') {
            $checks[] = str_replace('METHOD', $method, $count);
        }
        if ($with !== null) {
            $conditions = [];
            foreach ($with as $index => $argument) {
                $conditions[] = $this->constraint($argument, "(\$arguments[$index] ?? null)");
            }
            $checks[] = "isOnlyCalledWithMatchingArgs($method, fn(array \$arguments): bool => " . (implode(' && ', $conditions) ?: 'true') . ')';
        }
        if ($checks !== []) {
            $this->usesAssertMock = true;
            $this->deferred[] = "AssertMock::begin($variable)->" . implode('->', $checks) . ';';
        }

        $code = '';
        if ($plan !== null) {
            $this->usesMicroMock = true;
            $code = "MicroMock::of($variable)->{$plan[0]}($method, {$plan[1]})";
        }
        if ($hasSemicolon) {
            return [$code === '' ? '' : "$code;", (int) $end];
        }

        return [$code, $cursor];
    }

    /** `$this->once()` etc. as an AssertMock check (METHOD is filled in later), '' for any(). */
    private function countCheck(string $matcher): ?string
    {
        if (!preg_match('/^' . self::MARKER . '::(\w+)\((.*)\)$/s', $matcher, $match)) {
            $this->issues[] = "uses the invocation matcher $matcher, which migrate cannot translate.";

            return null;
        }

        return match (strtolower($match[1])) {
            'once' => 'isCalledOnce(METHOD)',
            'never' => 'isNotCalled(METHOD)',
            'any' => '',
            'exactly' => "isCalledTimes(METHOD, {$match[2]})",
            'atleastonce' => 'isCalledAtLeast(METHOD, 1)',
            'atleast' => "isCalledAtLeast(METHOD, {$match[2]})",
            'atmost' => "isCalledAtMost(METHOD, {$match[2]})",
            default => $this->unsupported("the invocation matcher \$this->{$match[1]}()"),
        };
    }

    /** ->will($this->returnValue(...)) and friends. @return array{string, string}|null */
    private function willPlan(string $stub, string $variable): ?array
    {
        if (!preg_match('/^' . self::MARKER . '::(\w+)\((.*)\)$/s', $stub, $match)) {
            $this->issues[] = "uses ->will($stub), which migrate cannot translate.";

            return null;
        }

        return match (strtolower($match[1])) {
            'returnvalue' => ['returns', $match[2]],
            'returncallback' => ['returnsCallback', $match[2]],
            'onconsecutivecalls' => ['returnsSequence', $match[2]],
            'throwexception' => ['throws', $match[2]],
            'returnself' => ['returnsCallback', "fn() => $variable"],
            'returnargument' => ['returnsCallback', "fn(mixed ...\$arguments) => \$arguments[{$match[2]}]"],
            'returnvaluemap' => ['returnsMap', $match[2]],
            default => $this->unsupported("->will(\$this->{$match[1]}())"),
        };
    }

    /** A with() argument as a condition on $value: plain values compare with ==, like PHPUnit. */
    private function constraint(string $argument, string $value): string
    {
        if (!preg_match('/^' . self::MARKER . '::(\w+)\((.*)\)$/s', $argument, $match)) {
            return "$value == $argument";
        }
        $inner = $match[2];

        return match (strtolower($match[1])) {
            'equalto' => "$value == $inner",
            'identicalto' => "$value === $inner",
            'anything' => 'true',
            'isnull' => "$value === null",
            'istrue' => "$value === true",
            'isfalse' => "$value === false",
            'isinstanceof' => str_ends_with($inner, '::class') ? "$value instanceof " . substr($inner, 0, -7) : "is_a($value, $inner)",
            'callback' => "($inner)($value)",
            'stringcontains' => "str_contains((string) $value, $inner)",
            'stringstartswith' => "str_starts_with((string) $value, $inner)",
            'greaterthan' => "$value > $inner",
            'lessthan' => "$value < $inner",
            'arrayhaskey' => "array_key_exists($inner, $value)",
            'countof' => "count($value) === $inner",
            'istype' => preg_match('/^[\'"](\w+)[\'"]$/', $inner, $type) ? "is_{$type[1]}($value)" : $this->unsupported("\$this->isType($inner)") ?? 'false',
            'logicalnot' => '!(' . $this->constraint($inner, $value) . ')',
            'logicaland', 'logicalor' => '(' . implode(strtolower($match[1]) === 'logicaland' ? ' && ' : ' || ', array_map(
                fn(string $part): string => $this->constraint($part, $value),
                $this->splitArguments($inner),
            )) . ')',
            default => $this->unsupported("the constraint \$this->{$match[1]}()") ?? 'false',
        };
    }

    /**
     * A marker: mock creation (with getMockBuilder's chain), or a leftover helper call.
     *
     * @param list<\PhpToken> $tokens
     * @return array{string, int}
     */
    private function marker(array $tokens, int $i): array
    {
        $colon = (int) Code::next($tokens, $i);
        $nameIndex = (int) Code::next($tokens, $colon);
        $open = (int) Code::next($tokens, $nameIndex);
        $close = Code::closing($tokens, $open);
        $arguments = array_map(static fn(array $range): string => trim(Code::text($tokens, $range[0], $range[1])), Code::arguments($tokens, $open, $close));
        $type = $arguments[0] ?? '';
        $name = strtolower($tokens[$nameIndex]->text);
        $original = [Code::text($tokens, $i, $close), $close];
        if ($name === 'getmockbuilder') {
            return $this->builderChain($tokens, $close, $type) ?? $original;
        }

        $built = match ($name) {
            'createmock', 'createstub', 'getmockforabstractclass' => "MockBuilder::create($type)->disableOriginalConstructor()",
            'createpartialmock' => "MockBuilder::create($type)->disableOriginalConstructor()->interceptOnly(" . $this->spread($arguments[1] ?? '[]') . ')',
            'createconfiguredmock', 'createconfiguredstub' => $this->configuredMock($name, $type, $arguments[1] ?? '[]'),
            default => null,
        };
        if ($built === null) {
            return $original;
        }
        $this->usesBuilder = true;

        return [$built . '->build()->newInstance()', $close];
    }

    /**
     * getMockBuilder(X::class)->...->getMock()
     *
     * @param list<\PhpToken> $tokens
     * @return array{string, int}|null
     */
    private function builderChain(array $tokens, int $cursor, string $type): ?array
    {
        $code = "MockBuilder::create($type)";
        while (true) {
            $arrow = Code::next($tokens, $cursor);
            if ($arrow === null || $tokens[$arrow]->id !== T_OBJECT_OPERATOR) {
                return null;
            }
            $nameIndex = (int) Code::next($tokens, $arrow);
            $open = (int) Code::next($tokens, $nameIndex);
            $close = Code::closing($tokens, $open);
            $argument = trim(Code::text($tokens, $open + 1, $close - 1));
            $cursor = $close;
            switch (strtolower($tokens[$nameIndex]->text)) {
                case 'disableoriginalconstructor':
                    $code .= '->disableOriginalConstructor()';
                    break;
                case 'enableoriginalconstructor':
                    break;
                case 'setconstructorargs':
                    $code .= "->withConstructorArgs($argument)";
                    break;
                case 'onlymethods':
                case 'setmethods':
                    $code .= '->interceptOnly(' . $this->spread($argument) . ')';
                    break;
                case 'getmock':
                case 'getmockforabstractclass':
                    $this->usesBuilder = true;

                    return [$code . '->build()->newInstance()', $cursor];
                default:
                    if (!in_array(strtolower($tokens[$nameIndex]->text), self::IGNORED_BUILDER_CALLS, true)) {
                        $this->issues[] = "uses ->{$tokens[$nameIndex]->text}() on a PHPUnit mock builder, which migrate cannot translate.";
                        $this->explained['getmockbuilder'] = true;

                        return null;
                    }
            }
        }
    }

    /** createConfiguredMock(X::class, ['method' => value, ...]); null when the configuration is not a literal array. */
    private function configuredMock(string $name, string $type, string $configuration): ?string
    {
        $tokens = Code::tokenizeFragment($configuration);
        if (($tokens[0]->text ?? '') !== '[' || Code::closing($tokens, 0) !== count($tokens) - 1) {
            $this->issues[] = 'creates a configured mock from a non-literal array; rewrite it with MockBuilder.';
            $this->explained[$name] = true;

            return null;
        }
        $code = "MockBuilder::create($type)->disableOriginalConstructor()";
        foreach (Code::arguments($tokens, 0, count($tokens) - 1) as [$from, $to]) {
            [$method, $value] = array_map('trim', explode('=>', Code::text($tokens, $from, $to), 2)) + [1 => 'null'];
            $code .= "->returns($method, $value)";
        }

        return $code;
    }

    /** "['a', 'b']" => "'a', 'b'"; any other expression is spread. */
    private function spread(string $array): string
    {
        $tokens = Code::tokenizeFragment($array);

        return ($tokens[0]->text ?? '') === '[' && Code::closing($tokens, 0) === count($tokens) - 1
            ? trim(Code::text($tokens, 1, count($tokens) - 2), " \n\r\t,")
            : "...$array";
    }

    /** @return list<string> */
    private function splitArguments(string $arguments): array
    {
        $tokens = Code::tokenizeFragment($arguments);

        return array_map(static fn(array $range): string => trim(Code::text($tokens, $range[0], $range[1])), Code::split($tokens));
    }

    private function unsupported(string $what): ?string
    {
        $this->issues[] = "uses $what, which migrate cannot translate.";

        return null;
    }
}
