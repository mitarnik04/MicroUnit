<?php

namespace MicroUnit\Migrate;

/**
 * A concrete PHPUnit test class with everything it inherits from base classes and traits that
 * migrate can see (the files being migrated). Framework base classes are not merged: MicroUnit
 * hosts them, and calls into them go through the framework proxy (Laravel::, Symfony::, ...).
 */
final readonly class TestClass
{
    /** Root base class (fully qualified) => [framework label, proxy class]. */
    private const array FRAMEWORK_BASES = [
        'illuminate\foundation\testing\testcase' => ['Laravel', 'MicroUnit\Integration\Laravel\Laravel'],
        'orchestra\testbench\testcase' => ['Laravel', 'MicroUnit\Integration\Laravel\Laravel'],
        'tests\testcase' => ['Laravel', 'MicroUnit\Integration\Laravel\Laravel'],
        'symfony\bundle\frameworkbundle\test\webtestcase' => ['Symfony', 'MicroUnit\Integration\Symfony\Symfony'],
        'symfony\bundle\frameworkbundle\test\kerneltestcase' => ['Symfony', 'MicroUnit\Integration\Symfony\Symfony'],
        'codeigniter\test\ciunittestcase' => ['CodeIgniter', 'MicroUnit\Integration\CodeIgniter\CodeIgniter'],
    ];

    private const array PHPUNIT_BASES = ['phpunit\framework\testcase', 'phpunit_framework_testcase'];

    /** Operators that change the variable in front of them. */
    public const array CHANGING_OPERATORS = ['=', '+=', '-=', '*=', '/=', '.=', '%=', '??=', '**=', '|=', '&=', '^=', '<<=', '>>=', '++', '--'];

    /**
     * @param array<string, array{ParsedMethod, ParsedClass}> $methods lowercase name => method and the class declaring it
     * @param array<string, list<array{ParsedMethod, ParsedClass}>> $definitions lowercase name => every definition, the class's own first (for parent:: calls)
     * @param array<string, array{ParsedProperty, ParsedClass}> $properties
     * @param array<string, array{string, ParsedClass}> $constants name => value and declaring class
     * @param list<string> $frameworkTraits fully qualified traits that stay on the hosted framework test case
     * @param list<string> $selfNames short names that refer to this class or a merged parent
     * @param list<string> $mergedClasses fully qualified names of this class and every parent and trait merged into it
     * @param list<string> $issues
     * @param bool $isTestCase whether it extends a test case (classes next to the tests may have test*() methods too)
     * @param array<string, string> $staticConstants static properties with a default that nothing assigns: name => default value code
     */
    private function __construct(
        public ParsedClass $class,
        public array $methods,
        public array $definitions,
        public array $properties,
        public array $constants,
        public array $frameworkTraits,
        public ?string $framework,
        public ?string $proxy,
        public array $selfNames,
        public array $mergedClasses,
        public array $issues,
        public bool $isTestCase,
        public array $staticConstants = [],
    ) {}

    /** @param array<string, ParsedClass> $index lowercase fully qualified name => class, for every parsed file */
    public static function merge(ParsedClass $class, array $index): self
    {
        $methods = [];
        $definitions = [];
        $properties = [];
        $constants = [];
        $frameworkTraits = [];
        $selfNames = [];
        $mergedClasses = [];
        $issues = [];
        $framework = null;
        $proxy = null;

        $current = $class;
        $seen = [];
        while ($current !== null && !isset($seen[strtolower($current->fullName())])) {
            $seen[strtolower($current->fullName())] = true;
            $selfNames[] = $current->name;
            $names = Names::of($current->namespace, $current->uses);

            foreach (self::withTraits($current, $index, $names, $frameworkTraits) as $member) {
                $mergedClasses[] = $member->fullName();
                foreach ($member->methods as $method) {
                    $methods[strtolower($method->name)] ??= [$method, $member];
                    $definitions[strtolower($method->name)][] = [$method, $member];
                }
                foreach ($member->properties as $property) {
                    $properties[$property->name] ??= [$property, $member];
                }
                foreach ($member->constants as $constant) {
                    $constants[$constant['name']] ??= [$constant['value'], $member];
                }
            }

            if ($current->parent === null) {
                break;
            }
            $parent = strtolower($names->resolve($current->parent));
            if (isset(self::FRAMEWORK_BASES[$parent])) {
                [$framework, $proxy] = self::FRAMEWORK_BASES[$parent];
                break;
            }
            if (in_array($parent, self::PHPUNIT_BASES, true)) {
                break;
            }
            if (!isset($index[$parent])) {
                $issues[] = "extends {$current->parent}, which is not among the migrated files; its helpers are not available.";
                break;
            }
            $current = $index[$parent];
        }

        [$methods, $definitions] = self::withClassHooks($methods, $definitions);

        // Static properties nothing changes act as constants (with late static binding: the child's value wins).
        $staticConstants = [];
        $written = null;
        foreach ($properties as $name => [$property]) {
            if ($property->isStatic && $property->default !== null) {
                $written ??= self::writtenStaticProperties($methods, $selfNames);
                if (!isset($written[$name])) {
                    $staticConstants[$name] = $property->default;
                }
            }
        }

        return new self($class, $methods, $definitions, $properties, $constants, array_values(array_unique($frameworkTraits)), $framework, $proxy, $selfNames, array_values(array_unique($mergedClasses)), $issues, $current->parent !== null, $staticConstants);
    }

    /**
     * Static properties some method may change: assigned (`self::$x = `, `self::$x[] = `, `++`),
     * referenced (`&self::$x`), unset, or passed to a by-reference parameter of a PHP function
     * (`sort(self::$x)`) or of a method of the class.
     *
     * @param array<string, array{ParsedMethod, ParsedClass}> $methods
     * @param list<string> $selfNames
     * @return array<string, true>
     */
    private static function writtenStaticProperties(array $methods, array $selfNames): array
    {
        $classNames = ['self', 'static', ...array_map(strtolower(...), $selfNames)];
        $written = [];
        foreach ($methods as [$method]) {
            if ($method->body === null || !str_contains($method->body, '::$')) {
                continue;
            }
            $tokens = Code::tokenizeBody($method->body);
            foreach ($tokens as $i => $token) {
                if ($token->id !== T_VARIABLE) {
                    continue;
                }
                $colon = Code::previous($tokens, $i);
                $class = $colon === null ? null : Code::previous($tokens, $colon);
                if ($class === null || $tokens[$colon]->id !== T_DOUBLE_COLON || !in_array(strtolower($tokens[$class]->text), $classNames, true)) {
                    continue;
                }
                if (self::isChanged($tokens, $class, $i, $methods)) {
                    $written[substr($token->text, 1)] = true;
                }
            }
        }

        return $written;
    }

    /**
     * @param list<\PhpToken> $tokens
     * @param array<string, array{ParsedMethod, ParsedClass}> $methods
     */
    private static function isChanged(array $tokens, int $classIndex, int $nameIndex, array $methods): bool
    {
        $after = Code::next($tokens, $nameIndex);
        while ($after !== null && Code::char($tokens[$after]) === '[') {
            $after = Code::next($tokens, Code::closing($tokens, $after));
        }
        if ($after !== null && $tokens[$after]->id !== T_ENCAPSED_AND_WHITESPACE && in_array($tokens[$after]->text, self::CHANGING_OPERATORS, true)) {
            return true;
        }
        $before = Code::previous($tokens, $classIndex);
        if ($before === null) {
            return false;
        }
        if (Code::char($tokens[$before]) === '&' || $tokens[$before]->id === T_INC || $tokens[$before]->id === T_DEC) {
            return true;
        }
        $isWholeArgument = in_array(Code::char($tokens[$before]), ['(', ','], true) && $after !== null && in_array(Code::char($tokens[$after]), [')', ','], true);
        if (!$isWholeArgument) {
            return false;
        }

        // Which call is it an argument of, and at which position?
        $position = 0;
        $open = $before;
        for ($depth = 0; $open >= 0; $open--) {
            $char = Code::char($tokens[$open]);
            if ($char === ')' || $char === ']' || $char === '}') {
                $depth++;
            } elseif (Code::isOpening($tokens[$open])) {
                if ($depth-- === 0) {
                    break;
                }
            } elseif ($char === ',' && $depth === 0) {
                $position++;
            }
        }
        $callee = $open < 0 || Code::char($tokens[$open]) !== '(' ? null : Code::previous($tokens, $open);
        if ($callee === null) {
            return false;
        }
        if ($tokens[$callee]->id === T_UNSET) {
            return true;
        }
        $receiver = Code::previous($tokens, $callee);
        $receiverId = $receiver === null ? null : $tokens[$receiver]->id;
        if ($tokens[$callee]->id === T_STRING && in_array($receiverId, [T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON], true)) {
            $method = $methods[strtolower($tokens[$callee]->text)][0] ?? null;

            return $method !== null && self::takesReference(array_map(
                static fn(string $parameter): bool => str_contains(explode('$', $parameter, 2)[0], '&'),
                self::parameterTexts($method->parameters),
            ), $position);
        }
        if (in_array($tokens[$callee]->id, [T_STRING, T_NAME_FULLY_QUALIFIED], true) && $receiverId !== T_NEW && $receiverId !== T_FUNCTION && function_exists(ltrim($tokens[$callee]->text, '\\'))) {
            $function = new \ReflectionFunction(ltrim($tokens[$callee]->text, '\\'));

            return $function->isInternal() && self::takesReference(array_map(
                static fn(\ReflectionParameter $parameter): bool => $parameter->isPassedByReference(),
                $function->getParameters(),
            ), $position);
        }

        return false;
    }

    /** @param list<bool> $byReference per parameter; the last one stands for variadic arguments beyond it */
    private static function takesReference(array $byReference, int $position): bool
    {
        return $byReference !== [] && ($byReference[$position] ?? $byReference[count($byReference) - 1]);
    }

    /** @return list<string> */
    private static function parameterTexts(string $parameters): array
    {
        $tokens = Code::tokenizeFragment($parameters);

        return array_map(static fn(array $range): string => Code::text($tokens, $range[0], $range[1]), Code::split($tokens));
    }

    /**
     * #[BeforeClass]/#[AfterClass] methods (any name) become part of setUpBeforeClass()/tearDownAfterClass(),
     * in PHPUnit's order: before-class hooks after setUpBeforeClass(), after-class hooks before tearDownAfterClass().
     *
     * @param array<string, array{ParsedMethod, ParsedClass}> $methods
     * @param array<string, list<array{ParsedMethod, ParsedClass}>> $definitions
     * @return array{array<string, array{ParsedMethod, ParsedClass}>, array<string, list<array{ParsedMethod, ParsedClass}>>}
     */
    private static function withClassHooks(array $methods, array $definitions): array
    {
        $hooks = ['setupbeforeclass' => ['BeforeClass', 'beforeClass', 'setUpBeforeClass'], 'teardownafterclass' => ['AfterClass', 'afterClass', 'tearDownAfterClass']];
        foreach ($hooks as $lifecycle => [$attribute, $annotation, $name]) {
            foreach ($methods as $lower => [$method, $owner]) {
                $isHook = $lower !== $lifecycle && $method->isStatic && $method->body !== null
                    && ($method->hasAttribute($attribute) || $method->annotation($annotation) !== []);
                if (!$isHook) {
                    continue;
                }
                unset($methods[$lower]);
                [$current, $currentOwner] = $definitions[$lifecycle][0] ?? [null, null];
                $isOwnLevel = $currentOwner !== null && $currentOwner->fullName() === $owner->fullName();
                // A hook in a class whose parent defines the lifecycle method gets its own level that calls the parent's.
                $before = $isOwnLevel ? substr((string) $current->body, 1, -1) : ($current === null ? '' : "parent::$name();");
                $hookBody = substr($method->body, 1, -1);
                $body = $lifecycle === 'setupbeforeclass' ? "$before\n$hookBody" : "$hookBody\n$before";
                $joined = new ParsedMethod($name, true, [], null, '', 'void', "{\n$body\n}", $method->bodyLine);
                if ($isOwnLevel) {
                    $definitions[$lifecycle][0] = [$joined, $owner];
                } else {
                    $definitions[$lifecycle] = [[$joined, $owner], ...$definitions[$lifecycle] ?? []];
                }
                $methods[$lifecycle] = [$joined, $owner];
            }
        }

        return [$methods, $definitions];
    }

    public function method(string $name): ?ParsedMethod
    {
        return $this->methods[strtolower($name)][0] ?? null;
    }

    /**
     * The class and the traits it uses (recursively) that migrate can see; other traits are noted
     * as framework traits.
     *
     * @param array<string, ParsedClass> $index
     * @param list<string> $frameworkTraits
     * @return list<ParsedClass>
     */
    private static function withTraits(ParsedClass $class, array $index, Names $names, array &$frameworkTraits): array
    {
        $members = [$class];
        foreach ($class->traits as $trait) {
            $fullName = $names->resolve($trait);
            if (isset($index[strtolower($fullName)])) {
                $traitClass = $index[strtolower($fullName)];
                $members = [...$members, ...self::withTraits($traitClass, $index, Names::of($traitClass->namespace, $traitClass->uses), $frameworkTraits)];
            } else {
                $frameworkTraits[] = $fullName;
            }
        }

        return $members;
    }
}
