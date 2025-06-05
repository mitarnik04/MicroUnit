<?php

/**
 * Entry point to load and run all tests.
 * Use in CLI or build pipelines to execute the full test suite.
 */

require_once __DIR__ . '/config/config-provider.php';
require_once __DIR__ . '/setup/test-setup.php';
require_once __DIR__ . '/helpers/utils.php';
require_once __DIR__ . '/config/config-builder.php';

$configFile = findFileInDirectoryOrAbove('microunit.config.php');

/** @var MicroUnitConfig $config */
$config = $configFile ? require_once $configFile : new MicroUnitConfig();
ConfigProvider::set($config);

$baseDir = $configFile ? dirname($configFile) : getcwd();
$testDir = $baseDir . DIRECTORY_SEPARATOR . ltrim($config->testDirectory ?? '', '\/');

$bootstrap = isset($config->bootstrapFile)
    ? $baseDir . DIRECTORY_SEPARATOR . ltrim($config->bootstrapFile, '\/')
    : null;

if ($bootstrap && file_exists($bootstrap)) {
    include_once $bootstrap;
}

$testFileRegexes = array_map(fn($glob) => globToRegex($glob), $config->testFilePatterns);
foreach (getFilesRecursive($testDir, $testFileRegexes) as $testFile) {
    require_once $testFile;
}
