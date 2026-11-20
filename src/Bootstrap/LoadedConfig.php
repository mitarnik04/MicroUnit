<?php

namespace MicroUnit\Bootstrap;

use MicroUnit\Config\MicroUnitConfig;
use MicroUnit\Helpers\Filesystem;

/** A configuration together with where it came from. */
final readonly class LoadedConfig
{
    public function __construct(
        public MicroUnitConfig $config,
        public ?string $file,
        public string $baseDirectory,
    ) {}

    /** Resolves a path from the config against the config file's directory. */
    public function resolve(string $path): string
    {
        return Filesystem::resolve($this->baseDirectory, $path);
    }
}
