<?php

namespace MicroUnit\Bootstrap;

use MicroUnit\Cache\ICache;

const AUTOLOADER_PATH_CACHE_KEY = 'autoloaderPath';

//Using require_once since 'use' isn't available without autoloader. 
require_once __DIR__ . '/../Helpers/Utils.php';



class AutoloadingInitializer
{
    public static function initAutoLoadig(?ICache $cache = null)
    {
        $autoloader = $cache?->get(AUTOLOADER_PATH_CACHE_KEY);

        if (is_null($autoloader) || !file_exists($autoloader)) {
            $autoloader = \MicroUnit\Helpers\Utils::findFileInDirectoryOrAbove('vendor/autoload.php', __DIR__);
            $cache?->set(AUTOLOADER_PATH_CACHE_KEY, $autoloader);
        };
        if (is_null($autoloader)) {
            fwrite(STDERR, 'Could not find the autoloader. Please run "composer install" first.');
            die(1);
        }

        require_once $autoloader;
    }
}
