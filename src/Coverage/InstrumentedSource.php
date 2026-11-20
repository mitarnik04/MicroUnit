<?php

namespace MicroUnit\Coverage;

/** Result of instrumenting one file: the probed code plus what is executable in it. */
final readonly class InstrumentedSource
{
    /**
     * @param list<int> $executableLines lines on which an executable statement starts
     * @param list<array{name: string, startLine: int, endLine: int}> $functions functions and methods with a body
     */
    public function __construct(
        public string $code,
        public array $executableLines,
        public array $functions,
    ) {}
}
