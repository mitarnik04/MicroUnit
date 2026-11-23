<?php

namespace MicroUnit\Assertion;

/** Fluent assertions for a path on disk. */
final readonly class AssertFile
{
    private function __construct(
        private string $path,
    ) {}

    public static function begin(string $path): self
    {
        return new self($path);
    }

    public function exists(string $message = ''): self
    {
        Assert::fileExists($this->path, $message);
        return $this;
    }

    public function notExists(string $message = ''): self
    {
        Assert::fileNotExists($this->path, $message);
        return $this;
    }

    public function isDirectory(string $message = ''): self
    {
        Assert::directoryExists($this->path, $message);
        return $this;
    }

    public function contains(string $needle, string $message = ''): self
    {
        Assert::fileContains($needle, $this->path, $message);
        return $this;
    }

    /** The file's contents equal $expected exactly (line endings normalized). */
    public function contentEquals(string $expected, string $message = ''): self
    {
        Assert::fileContentEquals($expected, $this->path, $message);
        return $this;
    }
}
