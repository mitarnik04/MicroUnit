<?php

namespace MicroUnit\Mock\Representation;

class MethodParameterRepresentation extends Representation
{
    public function __construct(
        public string $name,
        public string|array|null $type = null,
        public bool $isVariadic = false,
        public bool $isPassedByReference = false,
        public ?string $default = null,
        public ?TypeCombination $typeCombination = null
    ) {}

    public function asString(): string
    {
        $formattedType = isset($this->type) ? $this->asFormattedType($this->type, $this->typeCombination) : '';

        $prefix = '';
        if ($this->isPassedByReference) {
            $prefix .= '&';
        } else if ($this->isVariadic) {
            $prefix .= '...';
        }
        $prefix .= '$';

        $name = $prefix . $this->name;
        $result = "$formattedType $name";
        if (isset($default)) {
            $result .= " = $default";
        }
        return $result;
    }
}
