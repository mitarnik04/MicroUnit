<?php

/**
 * Entry point to load and run all tests.
 * Use in CLI or build pipelines to execute the full test suite.
 * 
 *  * NOTE: All test files must be named using the pattern `*-tests.php`
 *       so they are automatically discovered and executed.
 */

require_once __DIR__ . '/setup/test-setup.php';

/** 
 * Recursive function to find all *-tests.php files in tests directory and subdirectories
 * @param string $dir The root directory where to _start looking_
 */
function getTestFiles(string $dir): array
{
    $files = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));

    foreach ($iterator as $file) {
        if ($file->isFile() && preg_match('/-tests\.php$/', $file->getFilename())) {
            $files[] = $file->getPathname();
        }
    }

    return $files;
}

foreach (getTestFiles(__DIR__) as $testFile) {
    require_once $testFile;
}
