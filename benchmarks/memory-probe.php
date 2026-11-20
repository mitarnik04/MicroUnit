<?php

// Loaded with -d auto_prepend_file by the benchmark: appends the process's peak memory to the file
// named in MICROUNIT_BENCH_MEMORY_FILE when it ends. Identical for every tool.
//
// The peak is the operating system's peak resident set (getrusage's ru_maxrss), not
// memory_get_peak_usage(): PHPUnit 10+ calls memory_reset_peak_usage() before every test, so PHP's
// own counter would only report PHPUnit's last test. ru_maxrss cannot be reset from PHP. It also
// counts the PHP binary and its extensions, the same few MiB for every tool.
$microunitBenchMemoryFile = getenv('MICROUNIT_BENCH_MEMORY_FILE');
if (is_string($microunitBenchMemoryFile) && $microunitBenchMemoryFile !== '') {
    register_shutdown_function(static function () use ($microunitBenchMemoryFile): void {
        $maxRss = (int) (getrusage()['ru_maxrss'] ?? 0);
        $bytes = PHP_OS_FAMILY === 'Darwin' ? $maxRss : $maxRss * 1024;
        file_put_contents($microunitBenchMemoryFile, getmypid() . ' ' . $bytes . "\n", FILE_APPEND | LOCK_EX);
    });
}
unset($microunitBenchMemoryFile);
