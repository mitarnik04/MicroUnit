<?php
require_once __DIR__ . '/../config/config-provider.php';
require_once __DIR__ . '/../cache/json-cache.php';

const CONFIG_FILE_CACHE_KEY = 'configFilePath';

class ConfigInitializationResult
{
    public function __construct(
        public readonly MicroUnitConfig $config,
        public readonly ?string $configFullPath = null
    ) {}
}

function initConfiguration(): ConfigInitializationResult
{
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

    $configFileStatic = $configFile;

    /** @var MicroUnitConfig $config */
    $config = $configFile ? require_once $configFile : new MicroUnitConfig();
    ConfigProvider::set($config);

    if (!$isCachedFileValid) {
        $cache->set(CONFIG_FILE_CACHE_KEY, $configFile);
    }

    return new ConfigInitializationResult($config, $configFile);
}
