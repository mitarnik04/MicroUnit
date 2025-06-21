<?php

namespace MicroUnit\Toolkit;

class ManagedValue
{
    public function __construct(
        public mixed $value,
        public bool $readonly
    ) {}
}

class ValueBundle
{
    /** @var array<ManagedValue> */
    private array $values = [];

    public function __get(string $name): mixed
    {
        if ($this->tryGetValue($name, $managedValue)) {
            return $managedValue->value;
        }

        return null;
    }

    public function __set(string $name, mixed $value): void
    {
        $this->set($name, $value, false);
    }

    public function set(string $name, mixed $value, bool $readonly = false): void
    {
        if ($this->tryGetValue($name, $managedValue) && $managedValue->readonly) {
            throw new \RuntimeException("Readonly property '$name' cannot be modified");
        }

        $this->values[$name] = new ManagedValue($value, $readonly);
    }

    /**
     * Attempts to get the ManagedValue by name.
     *
     * @param ?ManagedValue &$output variable to assign found `ManagedValue` (assigns `null` if not found). 
     * @return bool True if the value exists, false otherwise.
     */
    private function tryGetValue(string $name, ?ManagedValue &$output): bool
    {
        $output = $this->values[$name] ?? null;
        return !is_null($output);
    }
}
