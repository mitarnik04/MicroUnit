<?php

/**
 * Entry point to load and run all tests.
 * Use in CLI or build pipelines to execute the full test suite.
 */

const SRC_DIR = __DIR__ . '/../src';
const RUN_LOG_FOLDER = __DIR__ . '/run_logs';

require_once SRC_DIR . '/setup/test-setup.php';
require_once SRC_DIR . '/helpers/utils.php';
require_once SRC_DIR . '/helpers/guid-generator.php';
require_once SRC_DIR . '/config/config-builder.php';
require_once SRC_DIR . '/bootstrap/config-initializer.php';
require_once SRC_DIR . '/bootstrap/logging-initializer.php';

if (!is_dir(RUN_LOG_FOLDER)) {
    mkdir(RUN_LOG_FOLDER);
}
setFileOnlyLogging(E_ALL, RUN_LOG_FOLDER . "/run-$runId.log");

$configInitResult = initConfiguration();
$config = $configInitResult->config;
$configFile = $configInitResult->configFullPath;

$runId = GuidGenerator::generateV4();

echo $config->persistRunLogs;
if (!$config->persistRunLogs) {
    deleteMatchingFiles(RUN_LOG_FOLDER . '/run-*.log');
}

$baseDir = $configFile ? dirname($configFile) : getcwd();
$testDir = $baseDir . DIRECTORY_SEPARATOR . ltrim($config->testDirectory ?? '', '\/');

$bootstrap = isset($config->bootstrapFile)
    ? $baseDir . DIRECTORY_SEPARATOR . ltrim($config->bootstrapFile, '\/')
    : null;

$doesBootstrapFileExist = file_exists($bootstrap);
if (!$doesBootstrapFileExist && $bootstrap) {
    trigger_error("The bootstrap file specified in $configFile does not exist.", E_USER_WARNING);
}

if ($bootstrap && file_exists($bootstrap)) {
    include_once $bootstrap;
}

$testFileRegexes = array_map(fn($glob) => globToRegex($glob), $config->testFilePatterns);
foreach (getFilesRecursive($testDir, $testFileRegexes) as $testFile) {
    require_once $testFile;
}
