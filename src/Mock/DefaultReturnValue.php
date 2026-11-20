<?php

namespace MicroUnit\Mock;

/**
 * Decides what an intercepted method returns when no return plan is configured: null where the
 * type allows it, a neutral value for scalars and arrays, the mock itself for fluent methods
 * (`static`, `self` or any type the mock satisfies). Anything else must be configured explicitly.
 *
 * @internal
 */
final class DefaultReturnValue
{
    private function __construct() {}

    /**
     * @param \ReflectionClass<object> $declaringClass
     * @return \Closure(object): mixed
     */
    public static function factoryFor(?\ReflectionType $type, \ReflectionClass $declaringClass, string $methodLabel): \Closure
    {
        if ($type === null || $type->allowsNull()) {
            return static fn(): mixed => null;
        }

        $members = $type instanceof \ReflectionUnionType ? $type->getTypes() : [$type];

        foreach ($members as $member) {
            if ($member instanceof \ReflectionNamedType && ($builtin = self::builtinFactory($member)) !== null) {
                return $builtin;
            }
        }

        $requiredClasses = array_map(
            static fn(\ReflectionType $member): string|array => self::classNames($member, $declaringClass),
            $members,
        );

        return static function (object $instance) use ($requiredClasses, $methodLabel, $type): object {
            foreach ($requiredClasses as $alternative) {
                $classes = (array) $alternative;
                if (array_all($classes, static fn(string $class): bool => $instance instanceof $class)) {
                    return $instance;
                }
            }

            throw new MockException(
                "$methodLabel must return $type, but no return value is configured. "
                . 'Use returns(), returnsSequence() or returnsCallback().',
            );
        };
    }

    /** @return (\Closure(object): mixed)|null */
    private static function builtinFactory(\ReflectionNamedType $type): ?\Closure
    {
        return match (strtolower($type->getName())) {
            'int' => static fn(): int => 0,
            'float' => static fn(): float => 0.0,
            'string' => static fn(): string => '',
            'bool', 'false' => static fn(): bool => false,
            'true' => static fn(): bool => true,
            'array', 'iterable' => static fn(): array => [],
            'void', 'never', 'null', 'mixed' => static fn(): mixed => null,
            'callable' => static fn(): \Closure => static fn(): mixed => null,
            'object' => static fn(object $instance): object => $instance,
            'static', 'self' => static fn(object $instance): object => $instance,
            default => null,
        };
    }

    /**
     * @param \ReflectionClass<object> $declaringClass
     * @return string|list<string> one class, or all classes of an intersection
     */
    private static function classNames(\ReflectionType $type, \ReflectionClass $declaringClass): string|array
    {
        if ($type instanceof \ReflectionIntersectionType) {
            return array_map(static fn(\ReflectionNamedType $member): string => $member->getName(), $type->getTypes());
        }

        /** @var \ReflectionNamedType $type */
        return strtolower($type->getName()) === 'parent'
            ? (string) $declaringClass->getParentClass()?->getName()
            : $type->getName();
    }
}
