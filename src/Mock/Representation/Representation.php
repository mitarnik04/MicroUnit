<?php

namespace MicroUnit\Mock\Representation;

use RuntimeException;

abstract class Representation
{
    private const PRIMITIVE_TYPES = ['int', 'float', 'string', 'bool', 'array', 'object', 'callable', 'iterable', 'mixed', 'void', 'never'];

    abstract function asString(): string;

    protected function asFormattedType(string|array $type, ?TypeCombination $combination = null): string
    {
        if (!is_array($type)) {
            $type = trim($type);
            return in_array($type, self::PRIMITIVE_TYPES) ? $type : '\\' . $type;
        }

        if (is_null($combination)) {
            throw new RuntimeException('Cannot specify multiple types without a typeCombination');
        }

        return array_reduce($type, function ($carry, $element) use ($combination) {
            $element = trim($element);
            $element =  in_array($element, self::PRIMITIVE_TYPES) ? $element : '\\' . $element;

            return $carry === ''
                ? $element
                : $carry . $combination->value . $element;
        }, '');
    }
}
