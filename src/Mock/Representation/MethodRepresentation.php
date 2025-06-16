<?php

namespace MicroUnit\Mock\Representation;

class MethodRepresentation extends ClassMemberRepresentation
{
    /** @param array<MethodParameterRepresentation> $methodParams */
    public function __construct(
        public AccessModifier $accessModifier,
        public string $methodName,
        public array $methodParams,
        public string|array|null $methodReturnType = null,
        public string $return = '',
        public bool $allowsNullReturn,
        public ?TypeCombination $typeCombination = null,
    ) {}

    public function asString(): string
    {
        $result = "{$this->accessModifier->value} function $this->methodName(";
        foreach ($this->methodParams as $param) {
            $result .= "{$param->asString()},";
        }
        $result = rtrim($result, ',');
        $result .= ')';

        if (isset($this->methodReturnType)) {
            $result .= ": {$this->asFormattedType($this->methodReturnType,$this->typeCombination)}";
        }
        $result .= "{
         \$this->traceCall(__FUNCTION__, func_get_args()); 
         return {$this->return}; 
         }";
        return $result;
    }
}
