<?php

namespace MicroUnit\Benchmarks;

/** One timed run of a command: wall-clock time, exit code, peak memory, test count, output. */
final readonly class Execution
{
    public function __construct(
        public float $seconds,
        public int $exitCode,
        public ?int $peakMemoryBytes,
        public ?int $tests,
        public string $output,
    ) {}

    /**
     * The current environment without the variables AI coding agents and CI set: tools change their
     * output for agents (PHPUnit and PHPStan detect them), so every tool runs as in a plain terminal.
     *
     * @return array<string, string>
     */
    private static function plainEnvironment(): array
    {
        return array_filter(getenv(), static fn(string $name): bool => preg_match(
            '/^(CLAUDE|AI_AGENT|AGENT_|AMP_|ANTIGRAVITY_|AUGMENT_|CLINE_|CODEX_|COPILOT_|CURSOR_|GEMINI_CLI|GOOSE_|GROK_|JUNIE_|OPENCLAW_|OPENCODE|PI_CODING_AGENT|WARP_CLI_AGENT|MICROUNIT_AGENT|CI$|GITHUB_ACTIONS|TF_BUILD)/i',
            $name,
        ) !== 1, ARRAY_FILTER_USE_KEY);
    }

    /**
     * Runs $command (no shell) in $directory and measures the wall-clock time from start to exit.
     *
     * @param list<string> $command
     * @param array<string, string> $environment added to the current environment
     */
    public static function run(array $command, string $directory, array $environment, string $memoryFile, int $timeoutSeconds = 1800): self
    {
        @unlink($memoryFile);
        $outputFile = tempnam(sys_get_temp_dir(), 'mub');
        $env = [...self::plainEnvironment(), ...$environment, 'MICROUNIT_BENCH_MEMORY_FILE' => $memoryFile, 'NO_COLOR' => '1'];

        $started = hrtime(true);
        $process = proc_open($command, [0 => ['file', PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null', 'r'], 1 => ['file', $outputFile, 'w'], 2 => ['file', $outputFile, 'a']], $pipes, $directory, $env);
        if ($process === false) {
            $length = strlen(implode(' ', $command));
            throw new \RuntimeException("Could not start a command of $length characters (Windows allows 32767): " . substr(implode(' ', $command), 0, 300));
        }
        do {
            $status = proc_get_status($process);
            if ($status['running'] && (hrtime(true) - $started) / 1e9 > $timeoutSeconds) {
                proc_terminate($process);
                throw new \RuntimeException("Timed out after {$timeoutSeconds}s: " . implode(' ', $command));
            }
            if ($status['running']) {
                usleep(1_000);
            }
        } while ($status['running']);
        $seconds = (hrtime(true) - $started) / 1e9;
        proc_close($process);

        $output = (string) file_get_contents($outputFile);
        @unlink($outputFile);
        $memory = null;
        foreach (file($memoryFile, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
            $memory = max($memory ?? 0, (int) explode(' ', $line)[1]);
        }

        return new self($seconds, $status['exitcode'], $memory, self::testCount($output), $output);
    }

    /** The number of tests in a JUnit report (the sum over its top-level suites), or null without a report. */
    public static function junitTestCount(string $file): ?int
    {
        $xml = is_file($file) ? @simplexml_load_file($file) : false;
        if ($xml === false) {
            return null;
        }

        return array_sum(array_map(static fn(\SimpleXMLElement $suite): int => (int) $suite['tests'], iterator_to_array($xml->testsuite, false)));
    }

    /** Failed and errored tests in a JUnit report, or null without a report. */
    public static function junitProblems(string $file): ?int
    {
        $xml = is_file($file) ? @simplexml_load_file($file) : false;
        if ($xml === false) {
            return null;
        }

        return array_sum(array_map(static fn(\SimpleXMLElement $suite): int => (int) $suite['failures'] + (int) $suite['errors'], iterator_to_array($xml->testsuite, false)));
    }

    /** The number of tests a run reports in its summary line (MicroUnit, PHPUnit). */
    public static function testCount(string $output): ?int
    {
        $output = (string) preg_replace('/\e\[[0-9;]*m/', '', $output);

        return match (true) {
            (bool) preg_match('/^Summary: \d+\/(\d+) passed/m', $output, $match) => (int) $match[1],
            (bool) preg_match('/^OK \((\d+) tests?,/m', $output, $match) => (int) $match[1],
            (bool) preg_match('/^Tests: (\d+), Assertions:/m', $output, $match) => (int) $match[1],
            default => null,
        };
    }
}
