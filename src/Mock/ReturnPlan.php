<?php

namespace MicroUnit\Mock;

/** What a mocked method does when it is called. Stateless: the call index is supplied by the caller. */
final readonly class ReturnPlan
{
    private function __construct(
        public ReturnPlanType $type,
        private mixed $data,
    ) {}

    public static function fixed(mixed $value): self
    {
        return new self(ReturnPlanType::Fixed, $value);
    }

    /** @param list<mixed> $values */
    public static function sequence(array $values): self
    {
        return new self(ReturnPlanType::Sequence, array_values($values));
    }

    public static function callback(callable $callback): self
    {
        return new self(ReturnPlanType::Callback, $callback(...));
    }

    /**
     * Rows of arguments followed by the return value; the first row whose arguments equal the
     * call's leading arguments (===) wins. Without a matching row the method returns its default.
     *
     * @param list<list<mixed>> $map
     */
    public static function map(array $map): self
    {
        return new self(ReturnPlanType::Map, array_map(
            static fn(array $row): array => [array_slice(array_values($row), 0, -1), $row === [] ? null : end($row)],
            $map,
        ));
    }

    public static function throwing(\Throwable $throwable): self
    {
        return new self(ReturnPlanType::Throwable, $throwable);
    }

    /**
     * The planned value for this call, wrapped in a one-element list, or null when the plan has
     * none (a sequence ran out, a map lacks a row for the arguments) and the caller falls back to
     * the default value. A callback runs, a throwing plan throws.
     *
     * @param list<mixed> $args
     * @param int $callIndex zero-based number of previous calls of this method
     * @return array{mixed}|null
     */
    public function lookup(array $args, int $callIndex): ?array
    {
        return match ($this->type) {
            ReturnPlanType::Fixed => [$this->data],
            ReturnPlanType::Sequence => array_key_exists($callIndex, $this->data) ? [$this->data[$callIndex]] : null,
            ReturnPlanType::Callback => [($this->data)(...$args)],
            ReturnPlanType::Map => $this->mapValue($args),
            ReturnPlanType::Throwable => throw $this->data,
        };
    }

    /**
     * @param list<mixed> $args
     * @return array{mixed}|null the value of the first row matching the call's leading arguments
     */
    private function mapValue(array $args): ?array
    {
        foreach ($this->data as [$rowArgs, $value]) {
            if ($rowArgs === array_slice($args, 0, count($rowArgs))) {
                return [$value];
            }
        }

        return null;
    }
}
