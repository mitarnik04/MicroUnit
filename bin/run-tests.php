<?php

/**
 * Entry point to load and run all tests.
 * Use in CLI or build pipelines to execute the full test suite.
 */

const SRC_DIR = __DIR__ . '/../src';
const RUN_LOG_FOLDER = __DIR__ . '/run_logs';

require_once SRC_DIR . '/setup/test-setup.php';
require_once SRC_DIR . '/helpers/utils.php';
require_once SRC_DIR . '/config/config-builder.php';
require_once SRC_DIR . '/bootstrap/config-initializer.php';
require_once SRC_DIR . '/bootstrap/logging-initializer.php';

if (!is_dir(RUN_LOG_FOLDER)) {
    mkdir(RUN_LOG_FOLDER);
}
$currentUtcDateTime = gmdate('Y-m-d\TH-i-s\Z');
setFileOnlyLogging(E_ALL, RUN_LOG_FOLDER . "/$currentUtcDateTime.log");

$configInitResult = initConfiguration();
$config = $configInitResult->config;
$configFile = $configInitResult->configFullPath;

if (!$config->persistRunLogs) {
    deleteMatchingFiles(RUN_LOG_FOLDER . '/*.log', [RUN_LOG_FOLDER . "/$currentUtcDateTime.log"]);
}

$baseDir = $configFile ? dirname($configFile) : getcwd();
$testDir = $baseDir . DIRECTORY_SEPARATOR . ltrim($config->testDirectory ?? '', '\/');

$bootstrap = isset($config->bootstrapFile)
    ? $baseDir . DIRECTORY_SEPARATOR . ltrim($config->bootstrapFile, '\/')
    : null;

$doesBootstrapFileExist = isset($bootstrap) && file_exists($bootstrap);
if (!$doesBootstrapFileExist && $bootstrap) {
    trigger_error("The bootstrap file specified in $configFile does not exist.", E_USER_WARNING);
}

if ($bootstrap && file_exists($bootstrap)) {
    include_once $bootstrap;
}

$testFileRegexes = array_map(fn($glob) => globToRegex($glob), $config->testFilePatterns);
$testFiles = getFilesRecursive($testDir, $testFileRegexes);
foreach ($testFiles as $testFile) {
    require_once $testFile;
}

echo count($testFiles);
if (count($testFiles) === 0) {
    trigger_error('No test files were found', E_USER_NOTICE);
}
