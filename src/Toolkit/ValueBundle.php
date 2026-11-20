<?php

namespace MicroUnit\Toolkit;

/**
 * A bag of named values with property-style access, handy for returning several values from setUp().
 * Values set with set($name, $value, readonly: true) cannot be overwritten.
 */
final class ValueBundle
{
    /** @var array<string, ManagedValue> */
    private array $values = [];

    public function __get(string $name): mixed
    {
        return ($this->values[$name] ?? null)?->value;
    }

    public function __set(string $name, mixed $value): void
    {
        $this->set($name, $value);
    }

    public function __isset(string $name): bool
    {
        return isset($this->values[$name]) && $this->values[$name]->value !== null;
    }

    public function set(string $name, mixed $value, bool $readonly = false): void
    {
        if (($this->values[$name] ?? null)?->isReadonly) {
            throw new \RuntimeException("Readonly value '$name' cannot be modified.");
        }

        $this->values[$name] = new ManagedValue($value, $readonly);
    }

    public function has(string $name): bool
    {
        return array_key_exists($name, $this->values);
    }
}
