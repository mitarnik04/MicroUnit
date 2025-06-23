<?php

namespace MicroUnit\Cache;

use MicroUnit\Helpers\Utils;

final class JsonCache implements ICache
{
    private ?array $cache;
    private readonly string $cacheFullPath;

    private static string $cacheDirectory = __DIR__;

    public static function setCacheDirectory(string $directory)
    {
        self::$cacheDirectory = $directory;
    }

    public function __construct(string $cacheFileName)
    {
        $this->cacheFullPath = rtrim(self::$cacheDirectory, '\/')
            . '/'
            . ltrim($cacheFileName, '\/')
            . '.json';

        if (!Utils::tryGetJsonContent($this->cacheFullPath, $this->cache, false)) {
            $this->cache = [];
        };
    }

    public function get(string $key, bool $throwOnNotFound = false): mixed
    {
        if ($this->hasKey($key)) {
            return json_decode($this->cache[$key]);
        }

        if ($throwOnNotFound) {
            throw new \RuntimeException("Cache does not contain key '$key'");
        }

        return null;
    }

    public function set(string $key, mixed $value, bool $throwOnKeyExists = false): void
    {
        if ($throwOnKeyExists && !$this->hasKey($key)) {
            throw new \RuntimeException("The key '$key' is already present in the cache");
        }

        $this->cache[$key] = json_encode($value);
        file_put_contents($this->cacheFullPath, json_encode($this->cache, JSON_PRETTY_PRINT));
    }

    public function hasKey(string $key): bool
    {
        return isset($this->cache) && array_key_exists($key, $this->cache);
    }
}
