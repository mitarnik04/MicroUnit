#!/usr/bin/env php
<?php

set_exception_handler(function (Throwable $e) {
    error_log($e);
    fwrite(STDERR, 'Something unexpected occured during test execution:' . PHP_EOL);
    fwrite(STDERR, '- Check for syntax errors in your test files' . PHP_EOL);
    fwrite(STDERR, '- Also check the run logs under \'microunit/bin/run_logs\' for more information' . PHP_EOL);
    die(1);
});

require_once __DIR__ . '/../src/Bootstrap/AutoloadingInitializer.php';
\MicroUnit\Bootstrap\AutoloadingInitializer::initAutoLoadig();

use MicroUnit\Helpers\Utils;
use MicroUnit\Bootstrap\ConfigInitializer;
use MicroUnit\Bootstrap\LoggingInitializer;

/**
 * Entry point to load and run all tests.
 * Use in CLI or build pipelines to execute the full test suite.
 */

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
