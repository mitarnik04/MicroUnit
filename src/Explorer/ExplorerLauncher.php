<?php

namespace MicroUnit\Explorer;

use MicroUnit\Helpers\PhpProcess;

/**
 * Makes sure the Test Explorer is running for this project and opens it in the browser. An
 * explorer that is already open simply picks up the new report (the same tab is reused).
 */
final readonly class ExplorerLauncher
{
    private const float STARTUP_TIMEOUT_IN_SECONDS = 5.0;

    /** @param list<string> $serverCommand */
    public function __construct(
        private ExplorerState $state,
        private int $port,
        private array $serverCommand,
    ) {}

    /** Local, interactive terminal sessions only: never in CI and never when output is piped. */
    public static function isInteractiveSession(): bool
    {
        return (string) getenv('CI') === '' && stream_isatty(STDOUT);
    }

    /** After a run: start the explorer the first time, otherwise just tell the user it updated. */
    public function publish(): void
    {
        $url = $this->runningServerUrl();
        if ($url !== null) {
            echo PHP_EOL . 'Test Explorer updated: ' . $url . PHP_EOL;
            return;
        }

        $this->open();
    }

    /** Starts the explorer if needed and opens it in the browser. */
    public function open(): bool
    {
        $url = $this->runningServerUrl() ?? $this->start();
        if ($url === null) {
            fwrite(STDERR, "The Test Explorer could not be started on port $this->port. See {$this->state->serverLogFile()}" . PHP_EOL);
            return false;
        }

        echo PHP_EOL . 'Test Explorer: ' . $url . PHP_EOL;
        $this->openBrowser($url);

        return true;
    }

    private function start(): ?string
    {
        PhpProcess::spawnDetached([...$this->serverCommand, "--port=$this->port"], $this->state->serverLogFile());

        $deadline = microtime(true) + self::STARTUP_TIMEOUT_IN_SECONDS;
        while (microtime(true) < $deadline) {
            usleep(50_000);
            $url = $this->runningServerUrl();
            if ($url !== null) {
                return $url;
            }
        }

        return null;
    }

    /** The URL of a live explorer server belonging to this project, if there is one. */
    private function runningServerUrl(): ?string
    {
        $server = $this->state->liveServer();

        return $server === null ? null : 'http://127.0.0.1:' . $server['port'] . '/';
    }

    private function openBrowser(string $url): void
    {
        $command = match (PHP_OS_FAMILY) {
            'Windows' => ['cmd', '/c', 'start', '""', $url],
            'Darwin' => ['open', $url],
            default => ['xdg-open', $url],
        };
        $process = @proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (is_resource($process)) {
            foreach ($pipes as $pipe) {
                fclose($pipe);
            }
            proc_close($process);
        }
    }
}
