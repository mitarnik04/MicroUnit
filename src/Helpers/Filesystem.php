<?php

namespace MicroUnit\Helpers;

final class Filesystem
{
    /** Directories never searched for test or source files. */
    private const array IGNORED_DIRECTORIES = ['vendor', 'node_modules', '.git', '.microunit'];

    private function __construct() {}

    /** Searches $startDirectory and then every parent directory for $fileName. */
    public static function findUpwards(string $fileName, string $startDirectory): ?string
    {
        $directory = $startDirectory;
        do {
            $candidate = $directory . DIRECTORY_SEPARATOR . $fileName;
            if (is_file($candidate)) {
                return $candidate;
            }
            $parent = dirname($directory);
            $isRoot = $parent === $directory;
            $directory = $parent;
        } while (!$isRoot);

        return null;
    }

    /**
     * Lists every file below $directory whose file name matches one of the regexes, sorted so
     * discovery order is identical on every platform.
     *
     * @param list<string> $fileNameRegexes
     * @return list<string>
     */
    public static function findFiles(string $directory, array $fileNameRegexes): array
    {
        if (is_file($directory)) {
            return [$directory];
        }

        $iterator = new \RecursiveIteratorIterator(new \RecursiveCallbackFilterIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            static fn(\SplFileInfo $entry): bool => !$entry->isDir() || !in_array($entry->getFilename(), self::IGNORED_DIRECTORIES, true),
        ));

        $files = [];
        foreach ($iterator as $file) {
            /** @var \SplFileInfo $file */
            $fileName = $file->getFilename();
            if (array_any($fileNameRegexes, static fn(string $regex): bool => preg_match($regex, $fileName) === 1)) {
                $files[] = $file->getPathname();
            }
        }
        sort($files, SORT_STRING);

        return $files;
    }

    public static function isAbsolute(string $path): bool
    {
        return str_starts_with($path, '/')
            || str_starts_with($path, '\\')
            || preg_match('~^[a-zA-Z]:[/\\\\]~', $path) === 1
            || str_contains($path, '://');
    }

    /** Resolves $path against $baseDirectory unless it is already absolute. */
    public static function resolve(string $baseDirectory, string $path): string
    {
        if (self::isAbsolute($path)) {
            return self::normalize($path);
        }

        return self::normalize($baseDirectory . DIRECTORY_SEPARATOR . $path);
    }

    /** Removes "." segments and redundant separators without touching the disk. */
    public static function normalize(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        $isUnc = str_starts_with($path, '//');
        $segments = [];
        foreach (explode('/', $path) as $index => $segment) {
            if ($segment === '.' || ($segment === '' && $index > 0)) {
                continue;
            }
            if ($segment === '..' && $segments !== [] && end($segments) !== '..' && end($segments) !== '') {
                array_pop($segments);
                continue;
            }
            $segments[] = $segment;
        }
        $normalized = implode('/', $segments);
        if ($isUnc) {
            $normalized = '/' . $normalized;
        }

        return str_replace('/', DIRECTORY_SEPARATOR, $normalized === '' ? '/' : $normalized);
    }

    /** $file relative to $directory, with forward slashes; files elsewhere keep their full path. */
    public static function relativeTo(string $file, string $directory): string
    {
        $file = str_replace('\\', '/', $file);
        $directory = rtrim(str_replace('\\', '/', $directory), '/') . '/';

        return $directory !== '/' && str_starts_with($file, $directory) ? substr($file, strlen($directory)) : $file;
    }

    public static function ensureDirectory(string $directory): void
    {
        if (!is_dir($directory) && !mkdir($directory, recursive: true) && !is_dir($directory)) {
            throw new \RuntimeException("Could not create directory '$directory'.");
        }
    }

    /** @param list<string> $keep absolute paths that must survive */
    public static function deleteMatching(string $globPattern, array $keep = []): void
    {
        foreach (array_diff(glob($globPattern) ?: [], $keep) as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }

    /**
     * Writes atomically so concurrent readers never see a half-written file. On Windows, a file that
     * was just written can be held briefly (by a virus scanner, for example), so the move is retried
     * for a moment before it fails.
     *
     * @throws \RuntimeException when the file cannot be written
     */
    public static function writeAtomically(string $file, string $contents): void
    {
        self::ensureDirectory(dirname($file));
        $temporaryFile = $file . '.' . getmypid() . '.tmp';
        if (@file_put_contents($temporaryFile, $contents) === false) {
            throw new \RuntimeException("Could not write $temporaryFile.");
        }
        for ($attempt = 1; !@rename($temporaryFile, $file); $attempt++) {
            if ($attempt === 5) {
                @unlink($temporaryFile);
                throw new \RuntimeException("Could not move the written file to $file.");
            }
            usleep(10_000 * $attempt);
        }
    }
}
