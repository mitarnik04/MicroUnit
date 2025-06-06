<?php

/**
 * Entry point to load and run all tests.
 * Use in CLI or build pipelines to execute the full test suite.
 */

require_once __DIR__ . '/config/config-provider.php';
require_once __DIR__ . '/setup/test-setup.php';
require_once __DIR__ . '/helpers/utils.php';
require_once __DIR__ . '/config/config-builder.php';
require_once __DIR__ . '/cache/json-cache.php';

const CONFIG_FILE_CACHE_KEY = "configFilePath";

$cache = new JsonCache('cache');

$cachedConfigFile = null;
if ($cache->hasKey(CONFIG_FILE_CACHE_KEY)) {
    $cachedConfigFile = $cache->get(CONFIG_FILE_CACHE_KEY, true);
}

//If the file got moved we want to rescan.
$isCachedFileValid = isset($cachedConfigFile) && file_exists($cachedConfigFile);
$configFile = $isCachedFileValid
    ? $cachedConfigFile
    : findFileInDirectoryOrAbove('microunit.config.php');

/** @var MicroUnitConfig $config */
$config = $configFile ? require_once $configFile : new MicroUnitConfig();
ConfigProvider::set($config);

if (!$isCachedFileValid && $config->cacheConfigPath) {
    $cache->set(CONFIG_FILE_CACHE_KEY, $configFile);
}

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
