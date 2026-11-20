<?php

namespace MicroUnit\Mock;

/**
 * Generates (via eval) a subclass or interface implementation for a MockDefinition. Classes
 * are cached per "shape" (target + generated methods), so building many mocks of the same type
 * costs one eval.
 *
 * @internal
 */
final class MockClassGenerator
{
    private const string GENERATED_NAMESPACE = 'MicroUnit\Mock\Generated';

    private const string REGISTRY = '\\' . MockRegistry::class;

    /** Magic methods that are never intercepted (lifecycle and serialization hooks). */
    private const array SKIPPED_METHODS = [
        '__construct', '__destruct', '__clone', '__sleep', '__wakeup',
        '__serialize', '__unserialize', '__set_state', '__debuginfo', '__callstatic',
    ];

    /** @var array<string, GeneratedMock> definition key => mock: a repeated build() needs no reflection */
    private static array $byDefinition = [];

    /** @var array<string, GeneratedMock> shape => mock: different definitions of the same shape share a class */
    private static array $byShape = [];

    private static int $generatedCount = 0;

    private function __construct() {}

    public static function generate(MockDefinition $definition): GeneratedMock
    {
        return self::$byDefinition[self::definitionKey($definition)] ??= self::generateFor($definition);
    }

    /** Everything about a definition that validation and the generated methods depend on. */
    private static function definitionKey(MockDefinition $definition): string
    {
        $sortedKeys = static function (array $methods): string {
            $names = array_keys($methods);
            sort($names);

            return implode(',', $names);
        };

        return $definition->targetType
            . '|' . $sortedKeys($definition->returnPlans)
            . '|' . $sortedKeys($definition->originalBehaviourMethods)
            . '|' . ($definition->interceptedMethods === null ? '*' : $sortedKeys($definition->interceptedMethods));
    }

    private static function generateFor(MockDefinition $definition): GeneratedMock
    {
        $class = self::reflect($definition->targetType);
        self::validate($class, $definition);

        $methods = self::methodsToGenerate($class, $definition);
        $shape = $class->getName() . '|' . implode(',', array_map(
            static fn(array $entry): string => $entry[0]->getName() . ':' . $entry[1]->name,
            $methods,
        ));

        return self::$byShape[$shape] ??= self::declare($class, $methods);
    }

    /** @return \ReflectionClass<object> */
    private static function reflect(string $targetType): \ReflectionClass
    {
        if (!class_exists($targetType) && !interface_exists($targetType)) {
            throw new MockException("Cannot mock '$targetType': no such class or interface.");
        }

        $class = new \ReflectionClass($targetType);
        $reason = match (true) {
            $class->isEnum() => 'enums cannot be extended',
            $class->isFinal() => 'it is final',
            default => null,
        };
        if ($reason !== null) {
            throw new MockException("Cannot mock {$class->getName()}: $reason.");
        }

        return $class;
    }

    /** @param \ReflectionClass<object> $class */
    private static function validate(\ReflectionClass $class, MockDefinition $definition): void
    {
        $configuredMethods = array_unique([
            ...array_keys($definition->returnPlans),
            ...array_keys($definition->originalBehaviourMethods),
        ]);

        foreach ($configuredMethods as $name) {
            if (!$class->hasMethod($name)) {
                if ($class->hasMethod('__call') && !$definition->keepsOriginalBehaviour($name)) {
                    continue; // handled by the intercepted __call
                }
                throw new MockException("Cannot configure {$class->getName()}::$name(): the method does not exist.");
            }

            $method = $class->getMethod($name);
            $problem = match (true) {
                $method->isStatic() => 'static methods cannot be mocked',
                $method->isPrivate() => 'private methods cannot be mocked',
                $method->isFinal() => 'final methods cannot be mocked',
                $method->isAbstract() && $definition->keepsOriginalBehaviour($name) => 'it is abstract, so there is no original behaviour to keep',
                default => null,
            };
            if ($problem !== null) {
                throw new MockException("Cannot configure {$class->getName()}::$name(): $problem.");
            }
        }
    }

    /**
     * @param \ReflectionClass<object> $class
     * @return list<array{\ReflectionMethod, InterceptionMode}>
     */
    private static function methodsToGenerate(\ReflectionClass $class, MockDefinition $definition): array
    {
        $methods = [];
        foreach ($class->getMethods() as $method) {
            $name = $method->getName();
            if ($method->isPrivate() || $method->isFinal() || in_array(strtolower($name), self::SKIPPED_METHODS, true)) {
                continue;
            }
            if ($method->isStatic()) {
                if ($method->isAbstract()) {
                    $methods[] = [$method, InterceptionMode::StaticStub];
                }
                continue;
            }
            // Protected concrete methods keep their real behaviour unless configured or named in interceptOnly().
            if ($method->isProtected() && !$method->isAbstract() && !$definition->hasPlan($name) && !isset($definition->interceptedMethods[strtolower($name)])) {
                continue;
            }

            $methods[] = [$method, match (true) {
                !$definition->runsOriginal($name, $method->isAbstract()) => InterceptionMode::Intercept,
                $definition->hasPlan($name) => InterceptionMode::OriginalThenPlan,
                default => InterceptionMode::Original,
            }];
        }

        return $methods;
    }

    /**
     * @param \ReflectionClass<object> $class
     * @param list<array{\ReflectionMethod, InterceptionMode}> $methods
     */
    private static function declare(\ReflectionClass $class, array $methods): GeneratedMock
    {
        $shortName = 'Mock_' . str_replace('\\', '_', $class->getName()) . '_' . ++self::$generatedCount;
        $relation = $class->isInterface() ? 'implements' : 'extends';
        $readonly = $class->isReadOnly() ? 'readonly ' : '';

        $body = '';
        $defaultReturns = [];
        foreach ($methods as [$method, $mode]) {
            $body .= self::methodCode($method, $mode);
            if ($mode === InterceptionMode::Intercept || $mode === InterceptionMode::OriginalThenPlan) {
                $defaultReturns[$method->getName()] = DefaultReturnValue::factoryFor(
                    self::returnTypeOf($method),
                    $method->getDeclaringClass(),
                    $class->getName() . '::' . $method->getName() . '()',
                );
            }
        }

        eval(sprintf(
            "namespace %s;\n\nfinal %sclass %s %s \\%s\n{\n%s}\n",
            self::GENERATED_NAMESPACE,
            $readonly,
            $shortName,
            $relation,
            $class->getName(),
            $body,
        ));

        /** @var class-string $className */
        $className = self::GENERATED_NAMESPACE . '\\' . $shortName;

        return new GeneratedMock($className, $defaultReturns);
    }

    private static function methodCode(\ReflectionMethod $method, InterceptionMode $mode): string
    {
        $name = $method->getName();
        $returnType = self::returnTypeOf($method);
        $returnTypeName = $returnType instanceof \ReflectionNamedType ? strtolower($returnType->getName()) : '';

        $signature = sprintf(
            '%s %sfunction %s%s(%s)%s',
            $method->isProtected() ? 'protected' : 'public',
            $method->isStatic() ? 'static ' : '',
            $method->returnsReference() ? '&' : '',
            $name,
            implode(', ', array_map(self::parameterCode(...), $method->getParameters())),
            $returnType === null ? '' : ': ' . TypeCode::of($returnType, $method->getDeclaringClass()),
        );

        $statements = self::bodyStatements($method, $mode, $returnTypeName);

        return "    $signature\n    {\n        " . implode("\n        ", $statements) . "\n    }\n\n";
    }

    /** @return list<string> */
    private static function bodyStatements(\ReflectionMethod $method, InterceptionMode $mode, string $returnTypeName): array
    {
        $name = $method->getName();
        if ($mode === InterceptionMode::StaticStub) {
            return ['throw new \\' . MockException::class . "('Static method $name() cannot be mocked.');"];
        }

        [$methodExpression, $argsExpression] = self::callDescription($method);
        $registryCall = static fn(string $operation): string => self::REGISTRY . "::$operation(\$this, $methodExpression, $argsExpression)";
        $parentCall = 'parent::' . $name . '(' . self::forwardedArguments($method) . ')';

        $returnsNothing = $returnTypeName === 'void' || $returnTypeName === 'never';
        $finish = static function (string $expression) use ($returnsNothing, $returnTypeName, $method, $name): array {
            if ($returnsNothing) {
                $statements = ["$expression;"];
                if ($returnTypeName === 'never') {
                    $statements[] = 'throw new \\' . MockException::class . "('$name() is declared never but returned; configure it with throws().');";
                }
                return $statements;
            }
            if ($method->returnsReference()) {
                return ["\$microUnitReturnValue = $expression;", 'return $microUnitReturnValue;'];
            }
            return ["return $expression;"];
        };

        return match ($mode) {
            InterceptionMode::Intercept => $finish($registryCall('invoke')),
            InterceptionMode::Original => [$registryCall('record') . ';', ...$finish($parentCall)],
            InterceptionMode::OriginalThenPlan => [$registryCall('record') . ';', "$parentCall;", ...$finish($registryCall('resolve'))],
        };
    }

    /**
     * The method name and argument list that get recorded. An intercepted __call() records the
     * magic method that was actually called, so `returns('magicMethod', ...)` works.
     *
     * @return array{string, string}
     */
    private static function callDescription(\ReflectionMethod $method): array
    {
        $parameters = $method->getParameters();
        if (strtolower($method->getName()) === '__call' && count($parameters) >= 2) {
            return ['$' . $parameters[0]->getName(), '\array_values($' . $parameters[1]->getName() . ')'];
        }

        return [var_export($method->getName(), true), '\func_get_args()'];
    }

    /** Forwards exactly the arguments the caller passed, keeping references intact. */
    private static function forwardedArguments(\ReflectionMethod $method): string
    {
        $parameters = $method->getParameters();
        if (!array_any($parameters, static fn(\ReflectionParameter $p): bool => $p->isPassedByReference())) {
            return '...\func_get_args()';
        }

        $fixed = array_filter($parameters, static fn(\ReflectionParameter $p): bool => !$p->isVariadic());
        $references = implode(', ', array_map(static fn(\ReflectionParameter $p): string => '&$' . $p->getName(), $fixed));
        $forwarded = "...\\array_slice([$references], 0, \\func_num_args())";

        $variadic = array_find($parameters, static fn(\ReflectionParameter $p): bool => $p->isVariadic());

        return $variadic === null ? $forwarded : $forwarded . ', ...$' . $variadic->getName();
    }

    private static function parameterCode(\ReflectionParameter $parameter): string
    {
        $type = $parameter->getType();
        $typeCode = $type === null ? '' : TypeCode::of($type, $parameter->getDeclaringClass()) . ' ';
        $default = '';

        if ($parameter->isOptional() && !$parameter->isVariadic()) {
            $literal = self::defaultValueLiteral($parameter);
            if ($literal === null) {
                // The default can't be written as code (e.g. `new Foo()`): widen the parameter, which
                // is always signature-compatible. Only passed arguments are forwarded, so the real
                // default still applies inside the original method.
                $typeCode = '';
                $literal = 'null';
            }
            $default = ' = ' . $literal;
        }

        return $typeCode
            . ($parameter->isPassedByReference() ? '&' : '')
            . ($parameter->isVariadic() ? '...' : '')
            . '$' . $parameter->getName()
            . $default;
    }

    private static function defaultValueLiteral(\ReflectionParameter $parameter): ?string
    {
        if (!$parameter->isDefaultValueAvailable()) {
            return null;
        }

        try {
            return self::literal($parameter->getDefaultValue());
        } catch (\Throwable) {
            return null;
        }
    }

    private static function literal(mixed $value): ?string
    {
        if ($value === null || is_scalar($value)) {
            return var_export($value, true);
        }
        if ($value instanceof \UnitEnum) {
            return '\\' . $value::class . '::' . $value->name;
        }
        if (!is_array($value)) {
            return null;
        }

        $elements = [];
        foreach ($value as $key => $element) {
            $elementLiteral = self::literal($element);
            if ($elementLiteral === null) {
                return null;
            }
            $elements[] = var_export($key, true) . ' => ' . $elementLiteral;
        }

        return '[' . implode(', ', $elements) . ']';
    }

    private static function returnTypeOf(\ReflectionMethod $method): ?\ReflectionType
    {
        return $method->getReturnType() ?? ($method->hasTentativeReturnType() ? $method->getTentativeReturnType() : null);
    }
}
