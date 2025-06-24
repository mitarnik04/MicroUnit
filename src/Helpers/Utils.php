<?php

namespace MicroUnit\Helpers;

class Utils
{

    /** 
     * Recursive function to find all test files in tests and subdirectories
     * @param string $dir The root directory where to _start looking_
     * @param array<string> $patterns all the valid naming patterns for test files
     */
    public static function getFilesRecursive(string $dir, array $patterns): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));

        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }

            $filename = $file->getFilename();

            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $filename)) {
                    $files[] = $file->getPathname();
                    break; // Stop checking once one pattern matches
                }
            }
        }

        return $files;
    }

    /** @param ?string $startDir the directory to start looking in. If null the current working directory is used */
    public static function findFileInDirectoryOrAbove(string $fileName, ?string $startDir = null): ?string
    {
        $dir = $startDir ?? getcwd();
        $previousDir = null;

        // Loop until root directory is reached
        while ($dir !== $previousDir) {
            $possibleConfig = $dir . DIRECTORY_SEPARATOR . $fileName;

            if (file_exists($possibleConfig)) {
                return $possibleConfig;
            }

            $previousDir = $dir;
            $dir = dirname($dir);  // Go one level up
        }

        return null; // Not found
    }

    public static function globToRegex(string $glob): string
    {
        $regex = preg_quote($glob, '/');
        $regex = str_replace('\*', '.*', $regex);
        $regex = str_replace('\?', '.', $regex);
        return '/^' . $regex . '$/i';
    }

    //TODO: Remove once JsonCache get's removed.
    public static function tryGetJsonContent(string $path, ?array &$jsonContentResult, bool $errorLogIfFileNotFound = true): bool
    {
        if (file_exists($path)) {
            $jsonContentResult = json_decode(file_get_contents($path), true);
            return true;
        }
        if ($errorLogIfFileNotFound) {
            error_log("JSON-file not found. PATH: $path");
        }
        return false;
    }

    public static function deleteMatchingFiles(string $pattern, array $filePathWhitelist = []): void
    {
        $files = array_diff(glob($pattern), $filePathWhitelist);

        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }

    public static function countLineNumbers(string $file): ?int
    {
        $lineCount = 0;

        $handle = fopen($file, 'r');
        if (!$handle) {
            return null;
        }

        while (!feof($handle)) {
            if (fgets($handle) !== false) {
                $lineCount++;
            }
        }

        fclose($handle);
        return $lineCount;
    }

    public static function createDirectoryIfNotExists(string $directory): void
    {
        if (!is_dir($directory)) {
            mkdir($directory, recursive: false);
        }
    }
}
