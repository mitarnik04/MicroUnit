<?php

namespace MicroUnit\Helpers;

/** Starting PHP subprocesses (parallel workers, explorer server) and facts about the machine. */
final class PhpProcess
{
    private function __construct() {}

    /**
     * @param list<string> $phpFlags e.g. ['-d', 'memory_limit=1G']
     * @return list<string>
     */
    public static function command(string $script, array $phpFlags = []): array
    {
        return [PHP_BINARY, ...self::inheritedIniFlags(), ...$phpFlags, $script];
    }

    public static function canSpawn(): bool
    {
        return function_exists('proc_open') && !in_array('proc_open', array_map('trim', explode(',', (string) ini_get('disable_functions'))), true);
    }

    public static function cpuCount(): int
    {
        $count = match (PHP_OS_FAMILY) {
            'Windows' => (int) getenv('NUMBER_OF_PROCESSORS'),
            'Darwin', 'BSD' => (int) @shell_exec('sysctl -n hw.ncpu'),
            default => is_readable('/proc/cpuinfo') ? substr_count((string) file_get_contents('/proc/cpuinfo'), "\nprocessor") + 1 : (int) @shell_exec('nproc'),
        };

        return max(1, $count);
    }

    /**
     * Starts a process that keeps running after this one exits.
     *
     * @param list<string> $command
     */
    public static function spawnDetached(array $command, string $logFile): void
    {
        $descriptors = [0 => ['file', PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null', 'r'], 1 => ['file', $logFile, 'a'], 2 => ['file', $logFile, 'a']];

        if (PHP_OS_FAMILY === 'Windows') {
            // Own process group: Ctrl+C in the terminal does not take the background process down.
            $process = proc_open($command, $descriptors, $pipes, null, null, ['bypass_shell' => true, 'create_process_group' => true]);
        } else {
            $shellCommand = 'nohup ' . implode(' ', array_map('escapeshellarg', $command)) . ' >/dev/null 2>&1 &';
            $process = proc_open(['/bin/sh', '-c', $shellCommand], $descriptors, $pipes);
        }

        // Deliberately not closed: proc_close() would wait for the process to finish.
        unset($process);
    }

    /**
     * The php.ini of this process (-c included) reaches the children, and so does its memory limit:
     * `php -d memory_limit=-1 vendor/bin/microunit --parallel` must not leave the workers at php.ini's
     * limit. Other -d flags cannot be read back and do not.
     *
     * @return list<string>
     */
    private static function inheritedIniFlags(): array
    {
        $iniFile = php_ini_loaded_file();

        return [...$iniFile === false ? ['-n'] : ['-c', $iniFile], '-d', 'memory_limit=' . ini_get('memory_limit')];
    }
}
