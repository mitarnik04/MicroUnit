<?php

/**
 * Entry point to load and run all tests.
 * Use in CLI or build pipelines to execute the full test suite.
 * 
 */


require_once __DIR__ . '/config/config-provider.php';
require_once __DIR__ . '/setup/test-setup.php';


/** 
 * Recursive function to find all test files in tests and subdirectories
 * @param string $dir The root directory where to _start looking_
 * @param array<string> $patterns all the valid naming patterns for test files
 */
function getTestFiles(string $dir, array $patterns): array
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

function FindFileInDirectoryOrAbove(string $fileName, ?string $startDir = null): ?string
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

$configFile = FindFileInDirectoryOrAbove('microunit-config.php');
/** @var MicroUnitConfig $config */
$config;
if ($configFile !== null) {
    $config = require_once $configFile;
} else {
    $config = new MicroUnitConfig();
}
ConfigProvider::set($config);

$testDir = $config->testDirectory;
$boootstrap = $config->bootstrapFile;
if (isset($configFile)) {
    $configDir = dirname($configFile);
    /** @var string */
    $testDir = $configDir . (str_starts_with($testDir, '/') ? $testDir : '/' . $testDir);

    if (isset($config->bootstrapFile)) {
        /** @var string */
        $boootstrap = $configDir . str_starts_with($boootstrap, '/') ? $boootstrap : '/' . $boootstrap;
        include_once $boootstrap;
    }
}

if (isset($config->bootstrapFile)) {
    include_once $boootstrap;
}

$testFileRegexes = array_map(fn($glob) => globToRegex($glob), $config->testFilePatterns);
foreach (getTestFiles($testDir, $testFileRegexes) as $testFile) {
    require_once $testFile;
}
