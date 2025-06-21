<?php

declare(strict_types=1);

namespace MicroUnit\Cache;

use MicroUnit\Binary\BinarySerializer;
use MicroUnit\Binary\Constants\BitUnits;
use MicroUnit\Binary\Constants\Formats;

/**
 * Uses file caching in a custom format (.mu) for maximum performance. 
 * 
 * Format: 
 * 
 * HEADER  
 * [4 bytes]  Magic: "MUCH"            ; MicroUnit Cache Header
 * [1 bit]    Version (e.g., 1)

 * ENTRY SECTION (fixed or grows only forward)  
 * [4 bytes]  Entry count (N)  
 * 
 * Repeated N times:  
 *   - [2 bytes] Key length (K)  
 *   - [K bytes] Key  
 *   - [8 bytes] Offset in file (uint64)   
 *   - [4 bytes] Length of value  

 *DATA SECTION  
 *Raw serialized values, append-only 
 */

final class MicroCache implements ICache
{
    private const MAGIC = 'MUCH';
    private const VERSION = 1;

    private string $filePath;
    /** @var resource */
    private $handle;

    /** @var array<string, array{offset: int, length: int}> */
    private array $entries = [];

    public function __construct(string $fileNameWithoutExtension)
    {
        //TODO: pass the full file path directly
        $this->filePath = __DIR__ . "/$fileNameWithoutExtension.mu";
        $this->handle = \fopen($this->filePath, 'c+b');

        if (!\is_resource($this->handle)) {
            throw new \RuntimeException("Unable to open cache file: {$this->filePath}");
        }

        $this->loadIndex();
    }

    public function get(string $key, bool $throwIfMissing = false): mixed
    {
        if ($this->hasKey($key)) {
            if ($throwIfMissing) {
                throw new \RuntimeException("Key '$key' not found.");
            }
            return null;
        }

        $value = $this->entries[$key];
        \fseek($this->handle, $value['offset']);
        $data = \fread($this->handle, $value['length']);

        if ($data === false || \strlen($data) < $value['length']) {
            throw new \RuntimeException("Failed to read data for key '$key'");
        }

        $offset = 0;
        return BinarySerializer::deserialize($data, $offset);
    }

    public function set(string $key, mixed $value, bool $throwIfExists = false): void
    {
        if ($throwIfExists && isset($this->entries[$key])) {
            throw new \RuntimeException("Key '$key' already exists.");
        }

        if (!\flock($this->handle, LOCK_EX)) {
            throw new \RuntimeException("Could not acquire file lock.");
        }

        $payload = BinarySerializer::serialize($value);

        \fseek($this->handle, 0, SEEK_END);
        $offset = \ftell($this->handle);
        \fwrite($this->handle, $payload);

        $this->entries[$key] = ['offset' => $offset, 'length' => \strlen($payload)];

        $this->writeHeaderAndIndex();

        \fflush($this->handle);
        \flock($this->handle, LOCK_UN);
    }

    public function hasKey(string $key): bool
    {
        return isset($this->entries[$key]);
    }

    private function loadIndex(): void
    {
        \rewind($this->handle);
        $magic = \fread($this->handle, BitUnits::HALF_BYTE);
        $versionByte = \fread($this->handle, 1);
        $version = $versionByte !== false ? \ord($versionByte) : 0;

        if ($magic !== self::MAGIC || $version !== self::VERSION) {
            // Clear file and start writing from scratch
            \rewind($this->handle);
            \ftruncate($this->handle, 0);
            \fwrite($this->handle, self::MAGIC . \chr(self::VERSION));
            \fwrite($this->handle, \pack('N', 0)); // Empty index
            \fflush($this->handle);
            return;
        }

        $entryLength = \fread($this->handle, BitUnits::HALF_BYTE);
        $entries = \unpack(Formats::UINT32, $entryLength)[1];

        for ($i = 0; $i < $entries; $i++) {
            $keyLen = \unpack(Formats::USHORT, \fread($this->handle, BitUnits::HALF_BYTE))[1];
            $key = \fread($this->handle, $keyLen);
            $offset = \unpack(Formats::UINT64, \fread($this->handle, BitUnits::BYTE))[1];
            $length = \unpack(Formats::UINT32, \fread($this->handle, BitUnits::HALF_BYTE))[1];
            $this->entries[$key] = ['offset' => $offset, 'length' => $length];
        }
    }

    private function writeHeaderAndIndex(): void
    {
        \rewind($this->handle);
        \fwrite($this->handle, self::MAGIC . \chr(self::VERSION));
        \fwrite($this->handle, \pack('N', \count($this->entries)));

        foreach ($this->entries as $key => $value) {
            \fwrite($this->handle, \pack(Formats::USHORT, \strlen($key)));
            \fwrite($this->handle, $key);
            \fwrite($this->handle, \pack(Formats::UINT64, $value['offset']));
            \fwrite($this->handle, \pack(Formats::UINT32, $value['length']));
        }
    }

    public function __destruct()
    {
        if (\is_resource($this->handle)) {
            \fclose($this->handle);
        }
    }
}
