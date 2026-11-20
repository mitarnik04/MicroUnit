<?php

namespace MicroUnit\Mock;

/**
 * Turns a reflected type back into PHP source code, resolving `self`/`parent` to the declaring
 * class (inside a generated subclass `self` would mean something narrower).
 *
 * @internal
 */
final class TypeCode
{
    private function __construct() {}

    /** @param \ReflectionClass<object>|null $declaringClass */
    public static function of(\ReflectionType $type, ?\ReflectionClass $declaringClass): string
    {
        return self::render($type, $declaringClass, false);
    }

    /** @param \ReflectionClass<object>|null $declaringClass */
    private static function render(\ReflectionType $type, ?\ReflectionClass $declaringClass, bool $isUnionMember): string
    {
        if ($type instanceof \ReflectionUnionType) {
            return implode('|', array_map(
                static fn(\ReflectionType $member): string => $member instanceof \ReflectionIntersectionType
                    ? '(' . self::render($member, $declaringClass, true) . ')'
                    : self::render($member, $declaringClass, true),
                $type->getTypes(),
            ));
        }

        if ($type instanceof \ReflectionIntersectionType) {
            return implode('&', array_map(
                static fn(\ReflectionType $member): string => self::render($member, $declaringClass, true),
                $type->getTypes(),
            ));
        }

        /** @var \ReflectionNamedType $type */
        $name = $type->getName();
        $code = match (strtolower($name)) {
            'self' => '\\' . $declaringClass?->getName(),
            'parent' => '\\' . ($declaringClass?->getParentClass() ?: throw new MockException('Type "parent" used without a parent class.'))->getName(),
            'static' => 'static',
            default => $type->isBuiltin() ? $name : '\\' . ltrim($name, '\\'),
        };

        $needsNullablePrefix = !$isUnionMember
            && $type->allowsNull()
            && !in_array(strtolower($name), ['null', 'mixed'], true);

        return ($needsNullablePrefix ? '?' : '') . $code;
    }
}
