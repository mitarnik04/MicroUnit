<?php

namespace MicroUnit\Mock;

class MockBuilder
{
    private string $type;
    private array $stubs = [];

    public static function for(string $type): self
    {
        return new self($type);
    }

    private function __construct(string $type)
    {
        if (!interface_exists($type) && !class_exists($type)) {
            throw new \InvalidArgumentException("Type $type does not exist.");
        }
        $this->type = $type;
    }

    public function stub(string $method, mixed $returnValue): self
    {
        $this->stubs[$method] = $returnValue;
        return $this;
    }

    public function build(): Mock
    {
        return MockFactory::create($this->type, $this->stubs);
    }
}
