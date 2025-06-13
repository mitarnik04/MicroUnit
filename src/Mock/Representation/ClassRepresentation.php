<?php

namespace MicroUnit\Mock\Representation;


class ClassRepresentation extends Representation
{
    public function __construct(
        public string $className,
        public array $classMembers = [],
        public ?string $extendedClassName = null,
        public ?string $implementedClassName = null,
    ) {}

    public function asString(): string
    {
        $result = "class {$this->className} ";
        if (isset($this->extendedClassName)) {
            $extended = $this->asFormattedType($this->extendedClassName);
            $result .= "extends $extended ";
        }
        if (isset($this->implementedClassName)) {
            $implemented = $this->asFormattedType($this->implementedClassName);
            $result .= "implements $implemented ";
        }
        $result .= '{';
        $membersAsString = array_map(fn($member) => $member->asString(), $this->classMembers);
        $result .= implode("\n", $membersAsString);
        $result .= PHP_EOL;
        $result .= '}';
        return $result;
    }
}
