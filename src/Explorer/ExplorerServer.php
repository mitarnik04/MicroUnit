<?php

namespace MicroUnit\Explorer;

use MicroUnit\Helpers\HtmlAssets;
use MicroUnit\MicroUnit;

/**
 * The Test Explorer's HTTP server: a single-threaded loop on 127.0.0.1 that serves the UI, the
 * latest report and starts test runs. Runs are child processes, so the UI stays responsive while
 * tests execute. Actions need the per-session token embedded in the page, and requests must name
 * this host (protection against cross-site requests and DNS rebinding).
 */
final class ExplorerServer
{
    private const int IDLE_TIMEOUT_IN_SECONDS = 1800;

    private const array REASONS = [200 => 'OK', 202 => 'Accepted', 403 => 'Forbidden', 404 => 'Not Found', 409 => 'Conflict', 500 => 'Internal Server Error'];

    private readonly string $token;

    /** @var resource|null the test run in progress */
    private $run = null;

    private ?int $lastExitCode = null;

    private int $lastActivityAt;

    /** @param list<string> $runnerCommand the CLI command (with --config) used for test runs */
    public function __construct(
        private readonly ExplorerState $state,
        private readonly int $port,
        private readonly array $runnerCommand,
    ) {
        $this->token = bin2hex(random_bytes(16));
        $this->lastActivityAt = time();
    }

    public function serve(): int
    {
        $socket = @stream_socket_server("tcp://127.0.0.1:$this->port", $errorCode, $errorMessage);
        if ($socket === false) {
            fwrite(STDERR, "Test Explorer: port $this->port is not available ($errorMessage)." . PHP_EOL);
            return 1;
        }

        $this->state->writeServer(['port' => $this->port, 'pid' => getmypid(), 'token' => $this->token, 'project' => $this->state->projectDirectory]);
        register_shutdown_function($this->state->clearServer(...));

        while (!$this->isIdle()) {
            $readable = [$socket];
            $write = $except = null;
            if (@stream_select($readable, $write, $except, 1) > 0) {
                $connection = @stream_socket_accept($socket, 0);
                if ($connection !== false) {
                    $this->handle($connection);
                    $this->lastActivityAt = time();
                }
            }
            $this->pollRun();
        }

        fclose($socket);
        $this->state->clearServer();

        return 0;
    }

    /** @param resource $connection */
    private function handle($connection): void
    {
        $request = HttpRequest::read($connection);
        if ($request === null) {
            fclose($connection);
            return;
        }

        try {
            [$status, $contentType, $body] = $this->isTrustedHost($request)
                ? $this->route($request)
                : [403, 'text/plain', 'Forbidden host.'];
        } catch (\Throwable $throwable) {
            // One bad request must not end the server.
            fwrite(STDERR, 'Test Explorer: ' . $request->method . ' ' . $request->path . ' failed: ' . $throwable->getMessage() . PHP_EOL);
            [$status, $contentType, $body] = [500, 'text/plain', 'Internal error.'];
        }

        @fwrite($connection, sprintf(
            "HTTP/1.1 %d %s\r\nContent-Type: %s\r\nContent-Length: %d\r\nCache-Control: no-store\r\nX-Content-Type-Options: nosniff\r\nX-Frame-Options: DENY\r\nConnection: close\r\n\r\n%s",
            $status,
            self::REASONS[$status] ?? 'Error',
            $contentType,
            strlen($body),
            $body,
        ));
        fclose($connection);
    }

    /** @return array{int, string, string} status, content type, body */
    private function route(HttpRequest $request): array
    {
        return match (true) {
            $request->method === 'GET' && $request->path === '/' => [200, 'text/html; charset=utf-8', $this->page()],
            $request->method === 'GET' && $request->path === '/api/state' => [200, 'application/json', $this->stateJson()],
            $request->method === 'GET' && $request->path === '/api/report' => [200, 'application/json', ExplorerState::encode($this->state->readReport() ?? [])],
            $request->method === 'POST' && $request->path === '/api/run' => $this->startRun($request),
            $request->method === 'GET' && str_starts_with($request->path, '/coverage/') => $this->coverageFile($request->path),
            default => [404, 'text/plain', 'Not found.'],
        };
    }

    /** @return array{int, string, string} */
    private function startRun(HttpRequest $request): array
    {
        if (!hash_equals($this->token, $request->header('x-microunit-token'))) {
            return [403, 'application/json', '{"error":"invalid token"}'];
        }
        if ($this->run !== null) {
            return [409, 'application/json', '{"error":"a run is already in progress"}'];
        }

        $options = json_decode($request->body, true) ?: [];
        $arguments = ['--explorer-run', '--no-explorer'];
        if (($options['failedOnly'] ?? false) === true) {
            $arguments[] = '--failed';
        }
        $ids = array_values(array_filter((array) ($options['ids'] ?? []), 'is_string'));
        if ($ids !== []) {
            // A file, not a --filter regex: thousands of ids would overflow the command line.
            $idsFile = $this->state->directory . '/run-ids.json';
            file_put_contents($idsFile, json_encode($ids, JSON_THROW_ON_ERROR));
            $arguments[] = '--ids-file=' . $idsFile;
        }

        $log = $this->state->directory . '/last-run.log';
        $this->run = proc_open(
            [...$this->runnerCommand, ...$arguments],
            // One handle for stdout and stderr: two would write over each other's output.
            [0 => ['file', PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null', 'r'], 1 => ['file', $log, 'w'], 2 => ['redirect', 1]],
            $pipes,
            $this->state->projectDirectory,
        ) ?: null;

        return $this->run === null
            ? [500, 'application/json', '{"error":"could not start the test run"}']
            : [202, 'application/json', '{"started":true}'];
    }

    private function pollRun(): void
    {
        if ($this->run === null) {
            return;
        }
        $status = proc_get_status($this->run);
        if (!$status['running']) {
            $this->lastExitCode = $status['exitcode'];
            proc_close($this->run);
            $this->run = null;
        }
    }

    private function stateJson(): string
    {
        return ExplorerState::encode([
            'revision' => $this->state->reportModifiedAt(),
            'isRunning' => $this->run !== null,
            'lastExitCode' => $this->lastExitCode,
        ]);
    }

    private function page(): string
    {
        return HtmlAssets::render('explorer.html', [
            'BOOT' => HtmlAssets::json([
                'token' => $this->token,
                'version' => MicroUnit::VERSION,
                'project' => $this->state->projectDirectory,
            ]),
        ]);
    }

    /** Serves the HTML coverage report written by the latest run, and nothing outside of it. @return array{int, string, string} */
    private function coverageFile(string $path): array
    {
        $index = $this->state->readReport()['coverage']['htmlReport'] ?? null;
        if (!is_string($index) || !is_file($index)) {
            return [404, 'text/plain', 'No HTML coverage report. Configure withCoverage(html: ...).'];
        }

        $root = realpath(dirname($index));
        $relative = rawurldecode(substr($path, strlen('/coverage/'))) ?: 'index.html';
        // Filesystem functions throw on NUL bytes.
        $file = $root === false || str_contains($relative, "\0") ? false : realpath($root . DIRECTORY_SEPARATOR . $relative);
        if ($root === false || $file === false || !str_starts_with($file, $root . DIRECTORY_SEPARATOR) || !is_file($file)) {
            return [404, 'text/plain', 'Not found.'];
        }

        return [200, 'text/html; charset=utf-8', (string) file_get_contents($file)];
    }

    private function isTrustedHost(HttpRequest $request): bool
    {
        return in_array($request->header('host'), ["127.0.0.1:$this->port", "localhost:$this->port"], true);
    }

    private function isIdle(): bool
    {
        return $this->run === null && time() - $this->lastActivityAt > self::IDLE_TIMEOUT_IN_SECONDS;
    }
}
