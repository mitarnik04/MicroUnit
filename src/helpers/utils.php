<?php

/** 
 * Recursive function to find all test files in tests and subdirectories
 * @param string $dir The root directory where to _start looking_
 * @param array<string> $patterns all the valid naming patterns for test files
 */
function getFilesRecursive(string $dir, array $patterns): array
{
    $files = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));

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
function findFileInDirectoryOrAbove(string $fileName, ?string $startDir = null): ?string
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

function globToRegex(string $glob): string
{
    $regex = preg_quote($glob, '/');
    $regex = str_replace('\*', '.*', $regex);
    $regex = str_replace('\?', '.', $regex);
    return '/^' . $regex . '$/i';
}
