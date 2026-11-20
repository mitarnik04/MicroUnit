<?php

namespace MicroUnit\Tests\Support;

use MicroUnit\Helpers\PhpProcess;

/** Runs bin/microunit in a fixture project (tests/Fixtures/projects/<name>), the way a user would. */
final class Cli
{
    private function __construct() {}

    /**
     * Human-readable output without explorer, whatever session the outer run is in.
     *
     * @param list<string> $arguments
     * @param array<string, string|null> $environment variables to set; null removes one
     */
    public static function run(string $project, array $arguments, array $environment = []): CliRun
    {
        $output = (string) tempnam(sys_get_temp_dir(), 'microunit-out');
        $errors = (string) tempnam(sys_get_temp_dir(), 'microunit-err');
        $process = proc_open(
            [...PhpProcess::command(dirname(__DIR__, 2) . '/bin/microunit'), '--no-agent', '--no-explorer', ...$arguments],
            [0 => ['file', PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null', 'r'], 1 => ['file', $output, 'w'], 2 => ['file', $errors, 'w']],
            $pipes,
            dirname(__DIR__) . '/Fixtures/projects/' . $project,
            array_filter([...getenv(), ...$environment], static fn(?string $value): bool => $value !== null),
        );
        if ($process === false) {
            throw new \RuntimeException('Could not start bin/microunit.');
        }
        $exitCode = proc_close($process);

        $run = new CliRun($exitCode, (string) file_get_contents($output), (string) file_get_contents($errors));
        unlink($output);
        unlink($errors);

        return $run;
    }
}
