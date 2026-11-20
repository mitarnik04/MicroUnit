<?php

namespace MicroUnit\Bootstrap;

use MicroUnit\Helpers\Filesystem;

/**
 * Sends PHP's diagnostics of a run into one log file (<runtime directory>/logs/<timestamp>.log) instead of the
 * console, and reports fatal errors in a readable way.
 */
final class RunLog
{
    private function __construct() {}

    public static function start(string $logDirectory, bool $keepPreviousLogs): void
    {
        Filesystem::ensureDirectory($logDirectory);
        $logFile = $logDirectory . '/' . gmdate('Y-m-d\TH-i-s\Z') . '-' . getmypid() . '.log';

        error_reporting(E_ALL);
        ini_set('display_errors', '0');
        ini_set('log_errors', '1');
        ini_set('error_log', $logFile);

        if (!$keepPreviousLogs) {
            Filesystem::deleteMatching($logDirectory . '/*.log', [$logFile]);
        }

        register_shutdown_function(static function () use ($logFile): void {
            $error = error_get_last();
            if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
                fwrite(STDERR, PHP_EOL . "MicroUnit stopped on a fatal error: {$error['message']}" . PHP_EOL
                    . "  in {$error['file']}:{$error['line']}" . PHP_EOL
                    . "  Full log: $logFile" . PHP_EOL);
            }
        });
    }
}
