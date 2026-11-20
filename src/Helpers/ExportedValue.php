<?php

namespace MicroUnit\Helpers;

/**
 * A value that has already been rendered to text, e.g. because it crossed a process boundary
 * (parallel workers) where the original value cannot travel. ValueExporter prints it verbatim.
 */
final readonly class ExportedValue
{
    /** As much as any output shows of a value (the largest display limit), so nothing is exported in vain. */
    public const int MAXIMUM_LENGTH = 20_000;

    public function __construct(
        public string $representation,
    ) {}

    public static function of(mixed $value): self
    {
        return $value instanceof self ? $value : new self(ValueExporter::export($value, self::MAXIMUM_LENGTH));
    }
}
