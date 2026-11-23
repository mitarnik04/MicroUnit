<?php

namespace MicroUnit\Migrate;

/**
 * Rewrites the code of one PHPUnit method into MicroUnit code: assertions become Assert:: calls,
 * $this->property becomes a variable, $this->helper() a function call, framework calls go through
 * the proxy. Records which properties, helpers and classes the code uses, and what it cannot
 * translate.
 */
final class BodyRewriter
{
    /** PHPUnit TestCase calls that have no MicroUnit equivalent and are dropped (statement only). */
    private const array DROPPED_CALLS = ['expectnottoperformassertions', 'doesnotperformassertions'];

    /** Mock APIs of PHPUnit itself; Mockery and framework mocks keep working unchanged. */
    private const array PHPUNIT_MOCK_CALLS = [
        'createmock', 'createstub', 'createconfiguredmock', 'createconfiguredstub', 'createpartialmock', 'createtestproxy',
        'getmockbuilder', 'getmockforabstractclass', 'getmockfortrait', 'getmockfromwsdl', 'prophesize', 'createmockforintersectionofinterfaces',
        'once', 'never', 'any', 'exactly', 'atleastonce', 'atleast', 'atmost', 'returnvalue', 'returncallback', 'returnself',
        'returnargument', 'returnvaluemap', 'onconsecutivecalls', 'throwexception', 'callback', 'equalto', 'identicalto',
        'isinstanceof', 'anything', 'logicaland', 'logicalor', 'logicalnot', 'stringcontains', 'isnull', 'istype',
    ];

    private const array BUILTIN_TYPES = ['int', 'float', 'string', 'bool', 'array', 'callable', 'iterable', 'object', 'mixed', 'void', 'never', 'null', 'false', 'true', 'self', 'static', 'parent'];

    /** @var array<string, true> properties the code reads or writes */
    public array $usedProperties = [];

    /** @var array<string, true> properties the code assigns ($this->p = ..., $this->p[] = ...) */
    public array $assignedProperties = [];

    /** @var array<string, true> helper functions the code calls (lowercase) */
    public array $calledHelpers = [];

    /** @var array<string, string> short name as written => fully qualified class name */
    public array $classReferences = [];

    /** @var array<string, string> lowercase function name => fully qualified function the code calls */
    public array $functionReferences = [];

    /** @var list<string> */
    public array $issues = [];

    /**
     * @param \Closure(string $helper): list<array{string, bool}> $stateOf a helper's state parameters: [property, by reference]
     * @param string $directory what __DIR__ becomes (the original folder, seen from the migrated file)
     * @param array<string, true> $functions lowercase fully qualified functions declared in the migrated files
     * @param array<string, string> $aliasRenames lowercase alias => new alias, for names MicroUnit's own classes use
     * @param (\Closure(string $alias, string $fullName): bool)|null $importer claims an alias in the migrated file; false when it already means another class
     */
    public function __construct(
        private readonly TestClass $class,
        private readonly Names $names,
        private readonly \Closure $stateOf,
        private readonly int $firstLine,
        private readonly string $directory = '__DIR__',
        private readonly ?string $file = null,
        private readonly array $functions = [],
        private readonly array $aliasRenames = [],
        private readonly ?string $methodName = null,
        private readonly ?\Closure $importer = null,
        private readonly int $methodLevel = 0,
        private readonly bool $isTestMethod = false,
    ) {}

    /**
     * Rewrites tokens $from..$to of $tokens (all inclusive).
     *
     * @param list<\PhpToken> $tokens
     */
    public function rewrite(array $tokens, int $from, int $to): string
    {
        $out = '';
        for ($i = $from; $i <= $to; $i++) {
            $token = $tokens[$i];

            if ($token->id === T_VARIABLE && $token->text === '$this') {
                [$text, $i] = $this->thisAccess($tokens, $i, $to);
                $out .= $text;
                continue;
            }
            if (($token->id === T_STRING || $token->id === T_STATIC || $token->id === T_NAME_QUALIFIED || $token->id === T_NAME_FULLY_QUALIFIED) && $this->isStaticAccess($tokens, $i, $to)) {
                [$text, $i] = $this->staticAccess($tokens, $i, $to);
                $out .= $text;
                continue;
            }
            if ($token->id === T_FUNCTION && $this->isClosure($tokens, $i)) {
                [$text, $i] = $this->closure($tokens, $i);
                $out .= $text;
                continue;
            }
            if ($token->id === T_CLASS_C || ($this->methodName !== null && ($token->id === T_METHOD_C || $token->id === T_FUNC_C))) {
                $class = $this->class->class->fullName();
                $out .= var_export(match ($token->id) {
                    T_CLASS_C => $class,
                    T_METHOD_C => "$class::{$this->methodName}",
                    default => $this->methodName,
                }, true);
                continue;
            }
            if ($token->id === T_DIR || $token->id === T_FILE) {
                $out .= $token->id === T_DIR ? $this->directory : ($this->file === null ? '__FILE__' : "{$this->directory} . '/" . basename($this->file) . "'");
                continue;
            }
            if (($token->id === T_STRING || $token->id === T_NAME_QUALIFIED) && $this->isClassPosition($tokens, $i)) {
                $out .= $this->classText($token->text);
                continue;
            }
            if ($token->id === T_STRING && $this->functions !== []) {
                $this->recordFunction($tokens, $i);
            }
            $out .= $token->text;
        }

        return $out;
    }

    /** Rewrites a piece of code that is not a statement on its own (a default value, a type, an argument). */
    public function rewriteFragment(string $code): string
    {
        $tokens = Code::tokenizeFragment($code);

        return trim($this->rewrite($tokens, 0, count($tokens) - 1));
    }

    public function issue(\PhpToken $token, string $message): void
    {
        $this->issues[] = 'line ' . ($this->firstLine + $token->line - 2) . ": $message";
    }

    /**
     * $this->name(...), $this->name or $this->name[...] = ...
     *
     * @param list<\PhpToken> $tokens
     * @return array{string, int}
     */
    private function thisAccess(array $tokens, int $i, int $to): array
    {
        $arrow = Code::next($tokens, $i);
        if ($arrow === null || $arrow > $to || !in_array($tokens[$arrow]->id, [T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR], true)) {
            $this->issue($tokens[$i], 'uses $this directly; there is no test case object in MicroUnit.');

            return ['$this', $i];
        }
        $nameIndex = (int) Code::next($tokens, $arrow);
        $name = $tokens[$nameIndex]->text;
        $after = Code::next($tokens, $nameIndex);

        if ($after !== null && Code::char($tokens[$after]) === '(') {
            return $this->methodCall($tokens, $nameIndex, $after, $tokens[$i]);
        }

        if ($this->isAssignment($tokens, $nameIndex)) {
            $this->assignedProperties[$name] = true;
        }
        if (isset($this->class->properties[$name])) {
            $this->usedProperties[$name] = true;

            return ['$' . $name, $nameIndex];
        }
        if ($this->class->proxy !== null && $name === 'app') {
            return ['app()', $nameIndex];
        }
        if ($this->class->proxy === null || !preg_match('/^[a-z_]\w*$/i', $name)) {
            $this->issue($tokens[$i], "reads \$this->$name, which is not a property of the class.");
        } else {
            $this->issue($tokens[$i], "reads \$this->$name from the framework test case; properties cannot be forwarded.");
        }
        $this->usedProperties[$name] = true;

        return ['$' . $name, $nameIndex];
    }

    /**
     * @param list<\PhpToken> $tokens
     * @return array{string, int}
     */
    private function methodCall(array $tokens, int $nameIndex, int $open, \PhpToken $origin): array
    {
        $name = $tokens[$nameIndex]->text;
        $lower = strtolower($name);
        $close = Code::closing($tokens, $open);
        $arguments = $this->rewrittenArguments($tokens, $open, $close);

        $helper = $this->class->method($name);
        if ($helper !== null && !AssertionMap::isAssertion($name)) {
            return [$this->helperCall($lower, $helper->name, $arguments), $close];
        }
        $assertion = AssertionMap::translate($name, $arguments);
        if ($assertion !== null) {
            return [$assertion, $close];
        }

        $translated = match ($lower) {
            'fail' => 'Assert::fail(' . implode(', ', $arguments) . ')',
            'marktestskipped' => 'Assert::skip(' . implode(', ', $arguments) . ')',
            'marktestincomplete' => "Assert::skip('Incomplete: ' . " . ($arguments[0] ?? "''") . ')',
            'addtoassertioncount' => '\MicroUnit\Core\TestContext::countAssertions(' . implode(', ', $arguments) . ')',
            'name', 'getname' => $this->isTestMethod && $this->methodName !== null
                ? var_export($this->methodName, true)
                : '\MicroUnit\Core\TestContext::current()?->name',
            default => null,
        };
        if ($translated !== null) {
            return [$translated, $close];
        }
        if (in_array($lower, self::DROPPED_CALLS, true)) {
            return ['null', $close];
        }
        if (in_array($lower, self::PHPUNIT_MOCK_CALLS, true)) {
            return [MockTranslator::MARKER . "::$name(" . implode(', ', $arguments) . ')', $close];
        }
        if (str_starts_with($lower, 'expect')) {
            $this->issue($origin, "calls \$this->$name() somewhere migrate cannot wrap (only at the top level of a test).");
        } elseif ($this->class->proxy !== null) {
            $this->recordProxy();

            return [$this->shortName($this->class->proxy) . "::$name(" . implode(', ', $arguments) . ')', $close];
        } else {
            $this->issue($origin, "calls \$this->$name(), which is neither a helper of the class nor a PHPUnit assertion.");
        }

        return ["\$this->$name(" . implode(', ', $arguments) . ')', $close];
    }

    /**
     * A call of a helper method's function: the properties it uses are passed first.
     *
     * @param string $key the helper as SuiteTranslator knows it: lowercase name, "#n" for a parent's version
     * @param list<string> $arguments
     */
    private function helperCall(string $key, string $function, array $arguments): string
    {
        $this->calledHelpers[$key] = true;
        $state = [];
        foreach (($this->stateOf)($key) as [$property]) {
            $this->usedProperties[$property] = true;
            $state[] = '$' . $property;
        }

        return $function . '(' . implode(', ', [...$state, ...$arguments]) . ')';
    }

    /** @param list<\PhpToken> $tokens */
    private function isStaticAccess(array $tokens, int $i, int $to): bool
    {
        $next = Code::next($tokens, $i);
        if ($next === null || $next > $to || $tokens[$next]->id !== T_DOUBLE_COLON) {
            return false;
        }
        $previous = Code::previous($tokens, $i);

        return $previous === null || ($tokens[$previous]->id !== T_NEW && $tokens[$previous]->id !== T_OBJECT_OPERATOR);
    }

    /**
     * self::x(), static::x(), ClassName::x(), Assert::assertX(), self::CONSTANT, self::$property
     *
     * @param list<\PhpToken> $tokens
     * @return array{string, int}
     */
    private function staticAccess(array $tokens, int $i, int $to): array
    {
        $class = $tokens[$i]->text;
        $colon = (int) Code::next($tokens, $i);
        $memberIndex = (int) Code::next($tokens, $colon);
        $member = $tokens[$memberIndex];
        $after = Code::next($tokens, $memberIndex);
        $isCall = $after !== null && Code::char($tokens[$after]) === '(';
        $lowerClass = strtolower(ltrim($class, '\\'));
        $isSelf = in_array($lowerClass, ['self', 'static'], true) || in_array($class, $this->class->selfNames, true);
        $isPhpUnitAssert = in_array($lowerClass, ['assert', 'phpunit\framework\assert', 'testcase', 'phpunit\framework\testcase'], true)
            && ($lowerClass !== 'assert' || strtolower($this->names->resolve($class)) === 'phpunit\framework\assert');

        if ($isCall && ($isSelf || $isPhpUnitAssert)) {
            $helper = $isSelf ? $this->class->method($member->text) : null;
            if ($helper !== null && !AssertionMap::isAssertion($member->text)) {
                $close = Code::closing($tokens, $after);

                return [$this->helperCall(strtolower($member->text), $helper->name, $this->rewrittenArguments($tokens, $after, $close)), $close];
            }

            return $this->methodCall($tokens, $memberIndex, $after, $tokens[$i]);
        }
        if ($isSelf && $member->id === T_STRING && strtolower($member->text) === 'class') {
            return ["'" . $this->class->class->fullName() . "'", $memberIndex];
        }
        if ($isSelf && $member->id === T_STRING && isset($this->class->constants[$member->text])) {
            return [$member->text, $memberIndex];
        }
        if ($isSelf && $member->id === T_VARIABLE) {
            $property = substr($member->text, 1);
            $constant = $this->class->staticConstants[$property] ?? null;
            if ($constant !== null && !$this->isAssignment($tokens, $memberIndex)) {
                $declaring = $this->class->properties[$property][1];
                $inner = $this->child(Names::of($declaring->namespace, $declaring->uses));
                $value = $inner->rewriteFragment($constant);
                $this->absorb($inner);

                return ["($value)", $memberIndex];
            }
            if (isset($this->class->properties[$property])) {
                $this->usedProperties[$property] = true;
                if ($this->isAssignment($tokens, $memberIndex)) {
                    $this->assignedProperties[$property] = true;
                }

                return ['$' . $property, $memberIndex];
            }
        }
        $parentLevel = $this->methodLevel + 1;
        if ($lowerClass === 'parent' && $isCall && isset($this->class->definitions[strtolower($member->text)][$parentLevel])) {
            // The parent's version of an overridden helper is a function of its own.
            $close = Code::closing($tokens, (int) $after);

            return [$this->helperCall(strtolower($member->text) . '#' . $parentLevel, Code::parentFunctionName($member->text, $parentLevel), $this->rewrittenArguments($tokens, (int) $after, $close)), $close];
        }
        if ($lowerClass === 'parent') {
            $this->issue($tokens[$i], "calls parent::{$member->text}; the parent class is gone after migration.");
        } elseif ($isSelf && $this->class->proxy !== null && $isCall) {
            $this->recordProxy();

            return [$this->shortName($this->class->proxy) . '::' . $member->text, $memberIndex];
        } elseif (!$isSelf && !in_array($lowerClass, ['self', 'static', 'parent'], true)) {
            $class = $this->classText($class);
        } elseif ($isSelf) {
            $this->issue($tokens[$i], "uses $class::{$member->text}, which migrate cannot translate.");
        }

        return [$class . '::' . $member->text, $memberIndex];
    }

    /**
     * A closure inside the code: its body is rewritten too, and the properties it now uses as
     * variables are added to its use list (by reference when it assigns them).
     *
     * @param list<\PhpToken> $tokens
     * @return array{string, int}
     */
    private function closure(array $tokens, int $functionIndex): array
    {
        $open = (int) Code::next($tokens, $functionIndex);
        if (Code::char($tokens[$open]) === '&') {
            $open = (int) Code::next($tokens, $open);
        }
        $paramsClose = Code::closing($tokens, $open);
        $cursor = (int) Code::next($tokens, $paramsClose);
        $existingUses = [];
        $useText = '';
        if ($tokens[$cursor]->id === T_USE) {
            $useOpen = (int) Code::next($tokens, $cursor);
            $useClose = Code::closing($tokens, $useOpen);
            foreach (Code::arguments($tokens, $useOpen, $useClose) as [$from, $to]) {
                $existingUses[ltrim(trim(Code::text($tokens, $from, $to)), '&$')] = trim(Code::text($tokens, $from, $to));
            }
            $cursor = (int) Code::next($tokens, $useClose);
        }
        $returnType = '';
        if (Code::char($tokens[$cursor]) === ':') {
            $typeStart = $cursor;
            while (Code::char($tokens[$cursor]) !== '{') {
                $cursor++;
            }
            $returnType = Code::text($tokens, $typeStart, $cursor - 1);
        }
        $bodyOpen = $cursor;
        $bodyClose = Code::closing($tokens, $bodyOpen);

        $inner = $this->child($this->names);
        $parameters = $inner->rewrite($tokens, $open + 1, $paramsClose - 1);
        $body = $inner->rewrite($tokens, $bodyOpen + 1, $bodyClose - 1);
        $this->absorb($inner);

        $uses = $existingUses;
        foreach (array_keys($inner->usedProperties) as $property) {
            $uses[$property] ??= (isset($inner->assignedProperties[$property]) ? '&$' : '$') . $property;
        }
        $useList = $uses === [] ? '' : ' use (' . implode(', ', $uses) . ')';
        $prefix = Code::text($tokens, $functionIndex, $open);

        return [$prefix . $parameters . ')' . $useList . $returnType . ' {' . $body . '}', $bodyClose];
    }

    /** A rewriter for code inside this code (a closure, a constant's value); absorb() collects what it found. */
    private function child(Names $names): self
    {
        return new self($this->class, $names, $this->stateOf, $this->firstLine, $this->directory, $this->file, $this->functions, $this->aliasRenames, $this->methodName, $this->importer, $this->methodLevel, $this->isTestMethod);
    }

    private function absorb(self $inner): void
    {
        $this->usedProperties += $inner->usedProperties;
        $this->assignedProperties += $inner->assignedProperties;
        $this->calledHelpers += $inner->calledHelpers;
        $this->classReferences += $inner->classReferences;
        $this->functionReferences += $inner->functionReferences;
        $this->issues = [...$this->issues, ...$inner->issues];
    }

    /**
     * @param list<\PhpToken> $tokens
     * @return list<string>
     */
    private function rewrittenArguments(array $tokens, int $open, int $close): array
    {
        return array_map(
            fn(array $range): string => trim($this->rewrite($tokens, $range[0], $range[1])),
            Code::arguments($tokens, $open, $close),
        );
    }

    /** @param list<\PhpToken> $tokens */
    private function isAssignment(array $tokens, int $nameIndex): bool
    {
        $next = Code::next($tokens, $nameIndex);
        while ($next !== null && Code::char($tokens[$next]) === '[') {
            $next = Code::next($tokens, Code::closing($tokens, $next));
        }

        return $next !== null && $tokens[$next]->id !== T_ENCAPSED_AND_WHITESPACE && in_array($tokens[$next]->text, TestClass::CHANGING_OPERATORS, true);
    }

    /** @param list<\PhpToken> $tokens */
    private function isClosure(array $tokens, int $i): bool
    {
        $next = Code::next($tokens, $i);

        return $next !== null && (Code::char($tokens[$next]) === '(' || Code::char($tokens[$next]) === '&');
    }

    /**
     * A name used as a class: after new/instanceof, in catch, as a parameter or return type.
     *
     * @param list<\PhpToken> $tokens
     */
    private function isClassPosition(array $tokens, int $i): bool
    {
        if (in_array(strtolower($tokens[$i]->text), self::BUILTIN_TYPES, true)) {
            return false;
        }
        $previous = Code::previous($tokens, $i);
        $next = Code::next($tokens, $i);
        $previousId = $previous === null ? null : $tokens[$previous]->id;
        $previousText = $previous === null ? '' : $tokens[$previous]->text;
        $nextId = $next === null ? null : $tokens[$next]->id;
        $nextText = $next === null ? '' : $tokens[$next]->text;

        return $previousId === T_NEW || $previousId === T_INSTANCEOF || $previousId === T_EXTENDS || $previousId === T_IMPLEMENTS
            || $nextId === T_VARIABLE || $nextId === T_ELLIPSIS
            || ($nextText === '|' || $nextText === '&') && $previousText !== '->'
            || (in_array($previousText, [':', '?', '|', '&'], true) && (in_array($nextText, ['{', '|', '&'], true) || $nextId === T_DOUBLE_ARROW))
            || ($previousText === '(' && $previous > 0 && Code::previous($tokens, $previous) !== null && $tokens[(int) Code::previous($tokens, $previous)]->id === T_CATCH);
    }

    /**
     * A class name as the migrated file must write it: through an import when its alias is free
     * there (renamed if MicroUnit's own classes use it), fully qualified when the alias already
     * means another class (a parent and a child class may import different classes as "Carbon").
     */
    private function classText(string $name): string
    {
        if (str_starts_with($name, '\\') || in_array(strtolower($name), self::BUILTIN_TYPES, true)) {
            return $name;
        }
        $parts = explode('\\', $name, 2);
        $alias = $this->aliasRenames[strtolower($parts[0])] ?? $parts[0];
        $fullName = $this->names->resolve($parts[0]);
        if ($this->importer !== null && !($this->importer)($alias, $fullName)) {
            return '\\' . $this->names->resolve($name);
        }
        $this->classReferences[$alias] = $fullName;

        return $alias . (isset($parts[1]) ? '\\' . $parts[1] : '');
    }

    /**
     * An unqualified call of a function declared in the migrated files (e.g. a helper in the test
     * bootstrap): the migrated file lives in another namespace, so it needs a `use function`.
     *
     * @param list<\PhpToken> $tokens
     */
    private function recordFunction(array $tokens, int $i): void
    {
        $next = Code::next($tokens, $i);
        $previous = Code::previous($tokens, $i);
        if ($next === null || Code::char($tokens[$next]) !== '(' || ($previous !== null && in_array($tokens[$previous]->id, [T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON, T_NEW, T_FUNCTION, T_FN], true))) {
            return;
        }
        $fullName = $this->names->resolve($tokens[$i]->text);
        if (isset($this->functions[strtolower($fullName)])) {
            $this->functionReferences[strtolower($tokens[$i]->text)] = $fullName;
        }
    }

    private function recordProxy(): void
    {
        $this->classReferences[$this->shortName((string) $this->class->proxy)] = (string) $this->class->proxy;
    }

    private function shortName(string $class): string
    {
        return substr($class, (int) strrpos($class, '\\') + 1);
    }
}
