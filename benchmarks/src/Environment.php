<?php

namespace MicroUnit\Benchmarks;

/**
 * Everything about the machine and PHP that can influence a measurement, recorded with every
 * result. No host name, user name or absolute path is recorded.
 */
final class Environment
{
    private function __construct() {}

    /** @return array<string, mixed> */
    public static function capture(string $php): array
    {
        return [
            'capturedAt' => date(DATE_ATOM),
            'machine' => self::machine(),
            'php' => self::php($php),
            'microunit' => self::microunit(),
        ];
    }

    /** @return array<string, mixed> */
    private static function machine(): array
    {
        return match (PHP_OS_FAMILY) {
            'Windows' => self::windows(),
            'Linux' => self::linux(),
            'Darwin' => self::macos(),
            default => ['os' => PHP_OS_FAMILY . ' ' . php_uname('r')],
        };
    }

    /** @return array<string, mixed> */
    private static function windows(): array
    {
        $info = json_decode((string) self::powershell(
            '$cpu = Get-CimInstance Win32_Processor | Select-Object -First 1; '
            . '$os = Get-CimInstance Win32_OperatingSystem; '
            . '$cs = Get-CimInstance Win32_ComputerSystem; '
            . '$defender = try { (Get-MpComputerStatus -ErrorAction Stop).RealTimeProtectionEnabled } catch { $null }; '
            . '[pscustomobject]@{ cpu = $cpu.Name.Trim(); cores = $cpu.NumberOfCores; logicalProcessors = $cpu.NumberOfLogicalProcessors; '
            . 'maxClockMhz = $cpu.MaxClockSpeed; loadPercent = $cpu.LoadPercentage; memoryBytes = $cs.TotalPhysicalMemory; '
            . 'os = $os.Caption; osVersion = $os.Version; osBuild = $os.BuildNumber; defenderRealTimeProtection = $defender } | ConvertTo-Json -Compress',
        ), true) ?: [];
        preg_match('/\((.+)\)\s*$/', (string) shell_exec('powercfg /getactivescheme 2>NUL'), $plan);

        return [...$info, 'powerPlan' => $plan[1] ?? null, 'onBattery' => self::windowsOnBattery()];
    }

    private static function windowsOnBattery(): ?bool
    {
        $status = trim((string) self::powershell('(Get-CimInstance Win32_Battery | Select-Object -First 1).BatteryStatus'));

        return $status === '' ? null : $status === '1'; // 1 = discharging
    }

    /** @return array<string, mixed> */
    private static function linux(): array
    {
        $cpuInfo = (string) @file_get_contents('/proc/cpuinfo');
        $memInfo = (string) @file_get_contents('/proc/meminfo');
        preg_match('/^model name\s*:\s*(.+)$/m', $cpuInfo, $model);
        preg_match('/^MemTotal:\s*(\d+)/m', $memInfo, $memory);
        preg_match('/^PRETTY_NAME="(.+)"$/m', (string) @file_get_contents('/etc/os-release'), $os);

        return [
            'cpu' => $model[1] ?? null,
            'logicalProcessors' => substr_count($cpuInfo, "\nprocessor") + (str_starts_with($cpuInfo, 'processor') ? 1 : 0),
            'memoryBytes' => isset($memory[1]) ? (int) $memory[1] * 1024 : null,
            'os' => ($os[1] ?? 'Linux') . ' (kernel ' . php_uname('r') . ')',
            'cpuGovernor' => trim((string) @file_get_contents('/sys/devices/system/cpu/cpu0/cpufreq/scaling_governor')) ?: null,
            'loadAverage' => sys_getloadavg() ?: null,
        ];
    }

    /** @return array<string, mixed> */
    private static function macos(): array
    {
        return [
            'cpu' => trim((string) shell_exec('sysctl -n machdep.cpu.brand_string')),
            'logicalProcessors' => (int) shell_exec('sysctl -n hw.logicalcpu'),
            'cores' => (int) shell_exec('sysctl -n hw.physicalcpu'),
            'memoryBytes' => (int) shell_exec('sysctl -n hw.memsize'),
            'os' => 'macOS ' . trim((string) shell_exec('sw_vers -productVersion')),
            'loadAverage' => sys_getloadavg() ?: null,
        ];
    }

    /** @return array<string, mixed> */
    private static function php(string $php): array
    {
        $probe = <<<'PHP'
            <?php
            echo json_encode([
                'version' => PHP_VERSION,
                'build' => (PHP_ZTS ? 'ZTS' : 'NTS') . ', ' . (PHP_DEBUG ? 'debug' : 'release') . ', ' . (PHP_INT_SIZE * 8) . '-bit',
                'sapi' => PHP_SAPI,
                'iniSha256' => php_ini_loaded_file() ? hash_file('sha256', php_ini_loaded_file()) : null,
                'extensions' => get_loaded_extensions(),
                'settings' => array_combine($names = ['memory_limit', 'opcache.enable', 'opcache.enable_cli', 'opcache.jit', 'opcache.jit_buffer_size', 'zend.assertions', 'xdebug.mode', 'pcov.enabled', 'realpath_cache_size'], array_map(static fn($name) => ini_get($name), $names)),
            ]);
            PHP;
        // A file, not -r: multi-line code does not survive the Windows command line.
        $file = sys_get_temp_dir() . '/microunit-bench-probe-' . getmypid() . '.php';
        file_put_contents($file, $probe);
        $output = shell_exec(escapeshellarg($php) . ' ' . escapeshellarg($file));
        @unlink($file);
        $info = json_decode((string) $output, true) ?: ['extensions' => []];
        sort($info['extensions']);

        return $info;
    }

    /** @return array<string, mixed> */
    private static function microunit(): array
    {
        $root = dirname(__DIR__, 2);
        $commit = trim((string) shell_exec('git -C ' . escapeshellarg($root) . ' rev-parse HEAD 2>' . (PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null')));
        $changes = trim((string) shell_exec('git -C ' . escapeshellarg($root) . ' status --porcelain -- src bin resources 2>' . (PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null')));
        require_once $root . '/src/MicroUnit.php';

        return [
            'version' => \MicroUnit\MicroUnit::VERSION,
            'commit' => $commit ?: null,
            'uncommittedChangesInSrc' => $changes !== '',
            'srcSha256' => self::directoryHash($root . '/src') . '+' . self::directoryHash($root . '/bin'),
        ];
    }

    /** A hash of every file in a directory: identifies the exact code that ran, committed or not. */
    private static function directoryHash(string $directory): string
    {
        $files = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS)) as $file) {
            $files[str_replace('\\', '/', substr($file->getPathname(), strlen($directory)))] = hash_file('sha256', $file->getPathname());
        }
        ksort($files);

        return substr(hash('sha256', json_encode($files)), 0, 16);
    }

    private static function powershell(string $script): ?string
    {
        return shell_exec('powershell -NoProfile -NonInteractive -Command ' . escapeshellarg($script)) ?: null;
    }
}
