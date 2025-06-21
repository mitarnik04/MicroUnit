#!/usr/bin/env php
<?php

const AUTOLOADER_PATH_CACHE_KEY = 'autoloaderPath';

//Using require_once since autoloader isn't available yet here. 
require_once __DIR__ . '/../src/Helpers/Utils.php';
require_once __DIR__ . '/../src/Cache/ICache.php';
require_once __DIR__ . '/../src/Cache/JsonCache.php';

set_exception_handler(function (Throwable $e) {
    error_log($e);
    fwrite(STDERR, 'Something unexpected occured during test execution:' . PHP_EOL);
    fwrite(STDERR, '- Check for syntax errors in your test files' . PHP_EOL);
    fwrite(STDERR, '- Also check the run logs under \'microunit/bin/run_logs\' for more information' . PHP_EOL);
    die(1);
});


$cache = new MicroUnit\Cache\JsonCache('cache');

$autoloader = $cache->get(AUTOLOADER_PATH_CACHE_KEY);

//If the file got moved to a new location or is not cached yet we want to rescan the directories to find it.
if (is_null($autoloader) || !file_exists($autoloader)) {
    $autoloader = MicroUnit\Helpers\Utils::findFileInDirectoryOrAbove('vendor/autoload.php', __DIR__);
    $cache->set(AUTOLOADER_PATH_CACHE_KEY, $autoloader);
};
if (is_null($autoloader)) {
    fwrite(STDERR, 'Could not find the autoloader. Please run "composer install" first.');
    die(1);
}

require_once $autoloader;

use MicroUnit\Helpers\Utils;
use MicroUnit\Bootstrap\ConfigInitializer;
use MicroUnit\Bootstrap\LoggingInitializer;

/**
 * Entry point to load and run all tests.
 * Use in CLI or build pipelines to execute the full test suite.
 */

const SRC_DIR = __DIR__ . '/../src';
const RUN_LOG_FOLDER = __DIR__ . '/run_logs';

if (!is_dir(RUN_LOG_FOLDER)) {
    mkdir(RUN_LOG_FOLDER);
}
$currentUtcDateTime = gmdate('Y-m-d\TH-i-s\Z');
LoggingInitializer::setFileOnlyLogging(E_ALL, RUN_LOG_FOLDER . "/$currentUtcDateTime.log");

$configInitResult = ConfigInitializer::initConfiguration();
$config = $configInitResult->config;
$configFile = $configInitResult->configFullPath;

if (!$config->persistRunLogs) {
    Utils::deleteMatchingFiles(RUN_LOG_FOLDER . '/*.log', [RUN_LOG_FOLDER . "/$currentUtcDateTime.log"]);
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

$testFileRegexes = array_map(fn($glob) => Utils::globToRegex($glob), $config->testFilePatterns);
$testFiles = Utils::getFilesRecursive($testDir, $testFileRegexes);
foreach ($testFiles as $testFile) {
    require_once $testFile;
}

if (count($testFiles) === 0) {
    trigger_error('No test files were found', E_USER_NOTICE);
}
