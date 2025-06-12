<?php

namespace MicroUnit\Bootstrap;

class LoggingInitializer
{
    public static function setFileOnlyLogging(int $errorReportingErrorLevel, string $logFile): void
    {
        error_reporting($errorReportingErrorLevel);
        ini_set('display_errors', '0');
        ini_set('log_errors', '1');
        ini_set('error_log',  $logFile);
    }
}
