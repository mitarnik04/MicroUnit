<?php

namespace MicroUnit\Core;

/** Serializable description of an unexpected throwable that made a test fail. */
final readonly class TestError
{
    public function __construct(
        public string $class,
        public string $message,
        public string $file,
        public int $line,
        public string $trace,
    ) {}

    public static function fromThrowable(\Throwable $throwable): self
    {
        return new self(
            $throwable::class,
            $throwable->getMessage(),
            $throwable->getFile(),
            $throwable->getLine(),
            $throwable->getTraceAsString(),
        );
    }
}
