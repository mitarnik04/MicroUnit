<?php

namespace MicroUnit\Assertion;

use MicroUnit\Helpers\Diff;
use MicroUnit\Helpers\ExportedValue;

final readonly class AssertionFailure
{
    public function __construct(
        public string $message,
        public mixed $expected = NotProvided::Value,
        public mixed $actual = NotProvided::Value,
        public ?Diff $diff = null,
        public string $customMessage = '',
    ) {}

    public function hasExpected(): bool
    {
        return $this->expected !== NotProvided::Value;
    }

    public function hasActual(): bool
    {
        return $this->actual !== NotProvided::Value;
    }

    /** The custom message (if any) followed by the assertion's own description. */
    public function fullMessage(): string
    {
        return $this->customMessage === '' ? $this->message : $this->customMessage . PHP_EOL . $this->message;
    }

    /** Replaces the compared values with their text form so the failure can cross process boundaries. */
    public function withExportedValues(): self
    {
        return new self(
            $this->message,
            $this->hasExpected() ? ExportedValue::of($this->expected) : NotProvided::Value,
            $this->hasActual() ? ExportedValue::of($this->actual) : NotProvided::Value,
            $this->diff,
            $this->customMessage,
        );
    }
}
