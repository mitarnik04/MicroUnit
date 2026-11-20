<?php

namespace MicroUnit\Toolkit;

use MicroUnit\Helpers\Filesystem;

/**
 * A fresh directory for tests that touch the file system.
 *
 *     $tester->fixture('dir', fn() => TemporaryDirectory::create(), cleanup: fn(TemporaryDirectory $dir) => $dir->delete());
 */
final readonly class TemporaryDirectory
{
    private function __construct(
        public string $root,
    ) {}

    public static function create(string $prefix = 'microunit'): self
    {
        $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $prefix . '-' . bin2hex(random_bytes(6));
        Filesystem::ensureDirectory($root);

        return new self($root);
    }

    /** Absolute path of $relativePath inside the directory. */
    public function path(string $relativePath = ''): string
    {
        return $relativePath === '' ? $this->root : Filesystem::resolve($this->root, $relativePath);
    }

    /** Writes a file (creating parent directories) and returns its absolute path. */
    public function write(string $relativePath, string $contents): string
    {
        $path = $this->path($relativePath);
        Filesystem::ensureDirectory(dirname($path));
        file_put_contents($path, $contents);

        return $path;
    }

    public function read(string $relativePath): string
    {
        $path = $this->path($relativePath);
        if (!is_file($path)) {
            throw new \RuntimeException("'$relativePath' does not exist in $this->root.");
        }

        return (string) file_get_contents($path);
    }

    public function exists(string $relativePath): bool
    {
        return file_exists($this->path($relativePath));
    }

    /** Removes the directory and everything in it. */
    public function delete(): void
    {
        if (!is_dir($this->root)) {
            return;
        }

        $entries = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($entries as $entry) {
            /** @var \SplFileInfo $entry */
            $entry->isDir() && !$entry->isLink() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
        }
        rmdir($this->root);
    }
}
