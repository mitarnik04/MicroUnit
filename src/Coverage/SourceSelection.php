<?php

namespace MicroUnit\Coverage;

use MicroUnit\Helpers\Filesystem;

/** Which PHP files are measured: everything under the include paths that is not excluded. */
final readonly class SourceSelection
{
    private const bool IS_CASE_INSENSITIVE = PHP_OS_FAMILY === 'Windows';

    /** @var list<string> normalised absolute paths */
    private array $include;

    /** @var list<string> comparable forms of $include: lower case on Windows, see comparable() */
    private array $comparableInclude;

    /** @var list<string> comparable forms of the excluded paths */
    private array $comparableExclude;

    /**
     * @param list<string> $include absolute files or directories
     * @param list<string> $exclude absolute files or directories inside $include
     */
    public function __construct(array $include, array $exclude = [])
    {
        $this->include = array_map(self::normalise(...), $include);
        $this->comparableInclude = array_map(self::comparable(...), $this->include);
        $this->comparableExclude = array_map(static fn(string $path): string => self::comparable(self::normalise($path)), $exclude);
    }

    public function contains(string $path): bool
    {
        return $this->measuredFile($path) !== null;
    }

    /** The normalised path when $path is measured, otherwise null. */
    public function measuredFile(string $path): ?string
    {
        $path = self::normalise($path);
        if (!str_ends_with($path, '.php')) {
            return null;
        }

        $comparablePath = self::comparable($path);
        $isWithin = static fn(string $root): bool => $comparablePath === $root || str_starts_with($comparablePath, rtrim($root, '/\\') . DIRECTORY_SEPARATOR);

        return array_any($this->comparableInclude, $isWithin) && !array_any($this->comparableExclude, $isWithin) ? $path : null;
    }

    /** @return list<string> every measured file that exists on disk */
    public function files(): array
    {
        $files = [];
        foreach ($this->include as $root) {
            if (!file_exists($root)) {
                continue;
            }
            foreach (Filesystem::findFiles($root, ['/\.php$/']) as $file) {
                $measured = $this->measuredFile($file);
                if ($measured !== null) {
                    $files[$measured] = true;
                }
            }
        }
        $files = array_keys($files);
        sort($files);

        return $files;
    }

    /** One spelling per file so paths from includes, globs and stack traces compare equal. */
    public static function normalise(string $path): string
    {
        return Filesystem::normalize(realpath($path) ?: $path);
    }

    private static function comparable(string $normalisedPath): string
    {
        return self::IS_CASE_INSENSITIVE ? strtolower($normalisedPath) : $normalisedPath;
    }
}
