<?php

namespace MicroUnit\Coverage;

use MicroUnit\Helpers\Filesystem;
use MicroUnit\MicroUnit;

/**
 * Instrumented files from earlier runs, keyed by path, size and modification time, and by the
 * instrumenter that produced them (MicroUnit version plus the instrumenter's own modification time,
 * so a changed instrumenter never serves stale code). Unchanged files skip tokenizing and
 * instrumenting entirely.
 */
final readonly class InstrumentationCache
{
    private string $instrumenterVersion;

    public function __construct(
        private string $directory,
    ) {
        $this->instrumenterVersion = MicroUnit::VERSION . '|' . (int) @filemtime(__DIR__ . DIRECTORY_SEPARATOR . 'Instrumenter.php');
    }

    public function get(string $file): ?InstrumentedSource
    {
        $entry = @file_get_contents($this->entryFor($file));
        if ($entry === false) {
            return null;
        }
        $cached = @unserialize($entry, ['allowed_classes' => [InstrumentedSource::class]]);

        return $cached instanceof InstrumentedSource ? $cached : null;
    }

    public function put(string $file, InstrumentedSource $source): void
    {
        Filesystem::writeAtomically($this->entryFor($file), serialize($source));
    }

    private function entryFor(string $file): string
    {
        $fingerprint = $file . '|' . (int) @filesize($file) . '|' . (int) @filemtime($file) . '|' . $this->instrumenterVersion;

        return $this->directory . DIRECTORY_SEPARATOR . hash('xxh128', $fingerprint) . '.cache';
    }
}
