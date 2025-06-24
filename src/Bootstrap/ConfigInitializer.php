<?php

namespace MicroUnit\Bootstrap;

use MicroUnit\Cache\ICache;
use MicroUnit\Config\ConfigProvider;
use MicroUnit\Config\MicroUnitConfig;
use MicroUnit\Helpers\Utils;

class ConfigInitializer
{
    private const CONFIG_FILE_CACHE_KEY = 'configFilePath';

    public static function initConfiguration(?ICache $cache = null): ConfigInitializationResult
    {
        $cachedConfigFile = null;
        if ($cache?->hasKey(self::CONFIG_FILE_CACHE_KEY)) {
            $cachedConfigFile = $cache?->get(self::CONFIG_FILE_CACHE_KEY, true);
        }

        //If the file got moved we want to rescan.
        $isCachedFileValid = isset($cachedConfigFile) && file_exists($cachedConfigFile);
        $configFile = $isCachedFileValid
            ? $cachedConfigFile
            : Utils::findFileInDirectoryOrAbove('microunit.config.php');

        $config = null;
        $usingDefaultConfig = false;
        if ($configFile) {
            trigger_error("Using configFile $configFile", E_USER_NOTICE);
            /** @var MicroUnitConfig $config */
            $config = require_once $configFile;
        } else {
            trigger_error('No config file found. Using default config', E_USER_NOTICE);
            $config = new MicroUnitConfig();
            $usingDefaultConfig = true;
        }
        ConfigProvider::set($config);

        if (!$usingDefaultConfig && !$isCachedFileValid) {
            trigger_error("Caching the config file path $configFile since it was either added or moved to a new location.", E_USER_NOTICE);
            $cache?->set(self::CONFIG_FILE_CACHE_KEY, $configFile);
        }

        return new ConfigInitializationResult($config, $configFile);
    }
}
