<?php

namespace MicroUnit\Cache;

use MicroUnit\Helpers\Utils;
use RuntimeException;

final class JsonCache implements ICache
{
    private static string $cacheDirectory;

    private ?array $cache;
    private readonly string $cacheFullPath;


    public static function setCacheDirectory(string $directory)
    {
        self::$cacheDirectory = $directory;
    }

    public function __construct(string $cacheFileName)
    {
        if (!isset(self::$cacheDirectory)) {
            throw new \RuntimeException('use setCacheDirectory to set the cash directory before caches can be created.');
        }

        $this->cacheFullPath = rtrim(self::$cacheDirectory, '\/')
            . '/'
            . ltrim($cacheFileName, '\/')
            . '.json';

        $this->cache = [];
        Utils::tryGetJsonContent($this->cacheFullPath, $this->cache, false);
    }

    public function get(string $key, bool $throwOnNotFound = false): mixed
    {
        if ($this->hasKey($key)) {
            return $this->cache[$key];
        }

        if ($throwOnNotFound) {
            throw new \RuntimeException("Cache does not contain key '$key'");
        }

        return null;
    }

    public function set(string $key, mixed $value, bool $throwOnKeyExists = false): void
    {
        if ($throwOnKeyExists && $this->hasKey($key)) {
            throw new \RuntimeException("The key '$key' is already present in the cache");
        }

        $this->cache[$key] = $value;

        if (!file_put_contents($this->cacheFullPath, json_encode($this->cache, JSON_PRETTY_PRINT))) {
            trigger_error("Couldn't write cache file '{$this->cacheFullPath}'.");
        };
    }

    public function hasKey(string $key): bool
    {
        return array_key_exists($key, $this->cache);
    }
}
