<?php

namespace MicroUnit\Bootstrap;

const AUTOLOADER_PATH_CACHE_KEY = 'autoloaderPath';
const CACHE_DIRECTORY = __DIR__ . '/../../bin/cache';

//Using require_once since 'use' isn't available without autoloader. 
require_once __DIR__ . '/../Helpers/Utils.php';
require_once __DIR__ . '/../Cache/ICache.php';
require_once __DIR__ . '/../Cache/JsonCache.php';


class AutoloadingInitializer
{
    public static function initAutoLoadig()
    {
        if (!is_dir(CACHE_DIRECTORY)) {
            mkdir(CACHE_DIRECTORY, recursive: false);
        }

        \MicroUnit\Cache\JsonCache::setCacheDirectory(CACHE_DIRECTORY);
        $cache = new \MicroUnit\Cache\JsonCache('cache');

        $autoloader = $cache->get(AUTOLOADER_PATH_CACHE_KEY);

        if (is_null($autoloader) || !file_exists($autoloader)) {
            $autoloader = \MicroUnit\Helpers\Utils::findFileInDirectoryOrAbove('vendor/autoload.php', __DIR__);
            $cache->set(AUTOLOADER_PATH_CACHE_KEY, $autoloader);
        };
        if (is_null($autoloader)) {
            fwrite(STDERR, 'Could not find the autoloader. Please run "composer install" first.');
            die(1);
        }

        require_once $autoloader;
    }
}
