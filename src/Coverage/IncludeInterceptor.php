<?php

namespace MicroUnit\Coverage;

/**
 * Stream wrapper that takes over `file://` so that `include`/`require` of measured source files
 * receive instrumented code. Every other file operation is passed straight to PHP's own wrapper.
 * __FILE__, __DIR__ and line numbers stay those of the original file.
 *
 * @internal
 */
final class IncludeInterceptor
{
    /** PHP passes STREAM_OPEN_FOR_INCLUDE (php_streams.h) to wrappers but does not expose the constant. */
    private const int OPEN_FOR_INCLUDE = 0x80;

    /** @var (\Closure(string $path): ?string)|null returns instrumented code, or null to load the file as is */
    private static ?\Closure $instrument = null;

    /**
     * Native handles PHP took the file descriptor of (stream_cast(), e.g. proc_open() with
     * ['file', ...] descriptors). PHP may release this wrapper right away while the descriptor is
     * still in use, so closing the handle with it would close the child process's output.
     *
     * @var list<resource>
     */
    private static array $castHandles = [];

    /** @var resource|null set by PHP for stream operations */
    public $context;

    /** @var resource|null native handle for everything that is not instrumented */
    private $handle = null;

    private ?string $buffer = null;

    private int $position = 0;

    private string $path = '';

    /** @param \Closure(string $path): ?string $instrument */
    public static function register(\Closure $instrument): void
    {
        self::$instrument = $instrument;
        stream_wrapper_unregister('file');
        stream_wrapper_register('file', self::class);
    }

    public static function unregister(): void
    {
        self::$instrument = null;
        @stream_wrapper_restore('file');
    }

    public function stream_open(string $path, string $mode, int $options, ?string &$openedPath): bool
    {
        $isInclude = ($options & self::OPEN_FOR_INCLUDE) !== 0;
        if ($isInclude && self::$instrument !== null) {
            $code = self::native(static fn() => (self::$instrument)($path));
            if ($code !== null) {
                $this->buffer = $code;
                $this->path = $path;
                return true;
            }
        }

        $useIncludePath = ($options & STREAM_USE_PATH) !== 0;
        $this->handle = self::native(fn() => $this->context === null
            ? @fopen($path, $mode, $useIncludePath)
            : @fopen($path, $mode, $useIncludePath, $this->context)) ?: null;

        return $this->handle !== null;
    }

    public function stream_read(int $count): string|false
    {
        if ($this->buffer !== null) {
            $chunk = substr($this->buffer, $this->position, $count);
            $this->position += strlen($chunk);
            return $chunk;
        }

        return fread($this->handle, $count);
    }

    public function stream_write(string $data): int|false
    {
        return $this->buffer === null ? fwrite($this->handle, $data) : false;
    }

    public function stream_eof(): bool
    {
        return $this->buffer !== null ? $this->position >= strlen($this->buffer) : feof($this->handle);
    }

    public function stream_tell(): int|false
    {
        return $this->buffer !== null ? $this->position : ftell($this->handle);
    }

    public function stream_seek(int $offset, int $whence = SEEK_SET): bool
    {
        if ($this->buffer === null) {
            return @fseek($this->handle, $offset, $whence) === 0; // devices like NUL cannot seek; false says so
        }

        $target = match ($whence) {
            SEEK_CUR => $this->position + $offset,
            SEEK_END => strlen($this->buffer) + $offset,
            default => $offset,
        };
        if ($target < 0) {
            return false;
        }
        $this->position = $target;

        return true;
    }

    /** @return array<int|string, int>|false */
    public function stream_stat(): array|false
    {
        if ($this->buffer === null) {
            return fstat($this->handle);
        }

        $stat = self::native(fn() => @stat($this->path)) ?: [];
        $stat[7] = $stat['size'] = strlen($this->buffer);

        return $stat;
    }

    public function stream_close(): void
    {
        if ($this->handle !== null && !in_array($this->handle, self::$castHandles, true)) {
            fclose($this->handle);
        }
        $this->handle = null;
        $this->buffer = null;
    }

    public function stream_flush(): bool
    {
        return $this->buffer !== null || fflush($this->handle);
    }

    public function stream_lock(int $operation): bool
    {
        return $this->buffer !== null || flock($this->handle, $operation);
    }

    public function stream_truncate(int $size): bool
    {
        return $this->buffer === null && ftruncate($this->handle, $size);
    }

    public function stream_set_option(int $option, int $arg1, ?int $arg2): bool
    {
        if ($this->buffer !== null || $this->handle === null) {
            return false;
        }

        return match ($option) {
            STREAM_OPTION_BLOCKING => stream_set_blocking($this->handle, (bool) $arg1),
            STREAM_OPTION_READ_TIMEOUT => stream_set_timeout($this->handle, $arg1, (int) $arg2),
            STREAM_OPTION_WRITE_BUFFER => stream_set_write_buffer($this->handle, $arg2 ?? 0) === 0,
            default => false,
        };
    }

    /** @return resource|false */
    public function stream_cast(int $castAs)
    {
        if ($this->handle !== null) {
            self::$castHandles[] = $this->handle;
        }

        return $this->handle ?? false;
    }

    public function stream_metadata(string $path, int $option, mixed $value): bool
    {
        return self::native(static fn(): bool => match ($option) {
            STREAM_META_TOUCH => @touch($path, ...array_slice((array) $value, 0, 2)),
            STREAM_META_OWNER_NAME, STREAM_META_OWNER => @chown($path, $value),
            STREAM_META_GROUP_NAME, STREAM_META_GROUP => @chgrp($path, $value),
            STREAM_META_ACCESS => @chmod($path, $value),
            default => false,
        });
    }

    /** @return array<int|string, int>|false */
    public function url_stat(string $path, int $flags): array|false
    {
        // Silenced either way (a missing file is not an error for file_exists() and friends), so one
        // system call is enough; lstat() also answers for a symlink whose target is gone.
        return ($flags & STREAM_URL_STAT_LINK) !== 0
            ? self::native(static fn() => @lstat($path))
            : self::native(static fn() => @stat($path));
    }

    public function unlink(string $path): bool
    {
        return self::native(static fn() => @unlink($path));
    }

    public function rename(string $from, string $to): bool
    {
        return self::native(static fn() => @rename($from, $to));
    }

    public function mkdir(string $path, int $mode, int $options): bool
    {
        return self::native(static fn() => @mkdir($path, $mode, ($options & STREAM_MKDIR_RECURSIVE) !== 0));
    }

    public function rmdir(string $path, int $options): bool
    {
        return self::native(static fn() => @rmdir($path));
    }

    public function dir_opendir(string $path, int $options): bool
    {
        $this->handle = self::native(static fn() => @opendir($path)) ?: null;

        return $this->handle !== null;
    }

    public function dir_readdir(): string|false
    {
        return readdir($this->handle);
    }

    public function dir_rewinddir(): bool
    {
        rewinddir($this->handle);

        return true;
    }

    public function dir_closedir(): bool
    {
        closedir($this->handle);
        $this->handle = null;

        return true;
    }

    /** Runs $operation with PHP's own file wrapper, then puts the interceptor back. */
    private static function native(\Closure $operation): mixed
    {
        stream_wrapper_restore('file');
        try {
            return $operation();
        } finally {
            if (self::$instrument !== null) {
                stream_wrapper_unregister('file');
                stream_wrapper_register('file', self::class);
            }
        }
    }
}
