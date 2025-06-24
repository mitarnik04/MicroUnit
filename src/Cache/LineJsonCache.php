<?php

namespace MicroUnit\Cache;

use MicroUnit\Helpers\Utils;
use RuntimeException;

final class LineJsonCache implements ICache
{
    private readonly string $cacheFullPath;

    private array $index = [];

    private float $compactationThreshold = 1.5;

    public function __construct(string $cacheDirectory, $cacheFileNameWithoutExtension)
    {
        $this->cacheFullPath = $cacheDirectory . '/' . $cacheFileNameWithoutExtension . '.jsonl';

        if (file_exists($this->cacheFullPath)) {
            $this->loadCache();
        }
    }


    public function get(string $key, bool $throwOnNotFound = false): mixed
    {
        if ($this->hasKey($key)) {
            return $this->index[$key];
        }

        if ($throwOnNotFound) {
            throw new RuntimeException("Cache key '$key' not found.");
        }

        return null;
    }

    public function set(string $key, mixed $value, bool $throwOnKeyExists = false): void
    {
        if ($throwOnKeyExists && $this->hasKey($key)) {
            throw new RuntimeException("Key '$key' already exists.");
        }

        if ($value instanceof \JsonSerializable) {
            $value = $value->jsonSerialize();
        }

        $line = json_encode(['key' => $key, 'value' => $value]) . PHP_EOL;

        if (file_put_contents($this->cacheFullPath, $line, FILE_APPEND | LOCK_EX) === false) {
            throw new RuntimeException("Failed to write to cache file: {$this->cacheFullPath}");
        }

        $this->index[$key] = $value;
    }

    public function hasKey(string $key): bool
    {
        return array_key_exists($key, $this->index);
    }

    /**
     * @param float $threshold The compaction trigger ratio (e.g., 1.3 means compact when file lines exceed keys by 30%)
     */
    public function setCompactationThreshold(float $threshold)
    {
        $this->compactationThreshold = $threshold;
    }

    private function loadCache(): void
    {
        $this->index = [];

        $handle = fopen($this->cacheFullPath, 'r');
        if (!$handle) {
            throw new RuntimeException("Could not open cache file: {$this->cacheFullPath}");
        }

        while (($line = fgets($handle)) !== false) {
            $entry = json_decode($line, true);
            if (isset($entry['key']) && array_key_exists('value', $entry)) {
                $this->index[$entry['key']] = $entry['value'];
            }
        }

        fclose($handle);
    }

    /** compacts the cache file if compaction is needed */
    public function autoCompact(): void
    {
        $lineCount = Utils::countLineNumbers($this->cacheFullPath);

        $keyCount = count($this->index);

        if ($lineCount <= $keyCount * $this->compactationThreshold) {
            return;
        }

        $tempPath = $this->cacheFullPath . '.tmp';
        $handle = fopen($tempPath, 'w');

        foreach ($this->index as $key => $value) {
            fwrite($handle, json_encode(['key' => $key, 'value' => $value]) . PHP_EOL);
        }

        fclose($handle);
        rename($tempPath, $this->cacheFullPath);
    }
}
