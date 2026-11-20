<?php

namespace MicroUnit\Migrate;

/** A class declaration as written in a PHPUnit test file. */
final readonly class ParsedClass
{
    /**
     * @param list<string> $uses top-level use statements, verbatim (without the trailing ";")
     * @param list<string> $traits trait names as written
     * @param list<array{name: string, value: string}> $constants
     * @param list<ParsedProperty> $properties
     * @param list<ParsedMethod> $methods
     * @param list<array{name: string, arguments: ?string}> $attributes class-level attributes
     */
    public function __construct(
        public string $file,
        public ?string $namespace,
        public array $uses,
        public bool $hasStrictTypes,
        public string $name,
        public ?string $parent,
        public bool $isAbstract,
        public array $traits,
        public array $constants,
        public array $properties,
        public array $methods,
        public array $attributes,
        public int $line,
    ) {}

    public function fullName(): string
    {
        return ($this->namespace === null ? '' : $this->namespace . '\\') . $this->name;
    }

    /** @return list<string> argument texts of every class attribute with this short name */
    public function attributeArguments(string $name): array
    {
        return ParsedMethod::argumentsOf($this->attributes, $name);
    }
}
