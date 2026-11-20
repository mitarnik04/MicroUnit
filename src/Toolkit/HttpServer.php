<?php

namespace MicroUnit\Toolkit;

use MicroUnit\Helpers\Filesystem;

/**
 * Runs your application with PHP's built-in web server on a free port.
 *
 *     $tester->fixture('app', fn() => HttpServer::start('public'), shared: true);
 *     $tester->define('home_page_loads', function (HttpServer $app) {
 *         AssertResponse::begin(Http::get($app->url('/')))->isOk();
 *     });
 *
 * The server stops when the fixture is cleaned up or the object goes out of scope.
 */
final class HttpServer
{
    private const float STARTUP_TIMEOUT_IN_SECONDS = 10.0;

    /** @var resource|null */
    private $process;

    /** @param resource $process */
    private function __construct(
        $process,
        public readonly string $host,
        public readonly int $port,
        private readonly string $logFile,
    ) {
        $this->process = $process;
    }

    /**
     * @param string $documentRootOrRouter a directory to serve, or a router script (e.g. public/index.php)
     * @param array<string, string> $environment extra environment variables for the application
     */
    public static function start(string $documentRootOrRouter, array $environment = [], string $host = '127.0.0.1'): self
    {
        $target = Filesystem::resolve((string) getcwd(), $documentRootOrRouter);
        if (!file_exists($target)) {
            throw new \InvalidArgumentException("Cannot serve '$documentRootOrRouter': it does not exist.");
        }

        $documentRoot = is_dir($target) ? $target : dirname($target);
        $port = self::freePort($host);
        $command = [PHP_BINARY, '-S', "$host:$port", '-t', $documentRoot];
        if (is_file($target)) {
            $command[] = $target;
        }

        $logFile = (string) tempnam(sys_get_temp_dir(), 'microunit-server-');
        $process = proc_open(
            $command,
            [0 => ['file', PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null', 'r'], 1 => ['file', $logFile, 'a'], 2 => ['file', $logFile, 'a']],
            $pipes,
            $documentRoot,
            [...getenv(), ...$environment],
            ['bypass_shell' => true],
        );
        if ($process === false) {
            throw new \RuntimeException('Could not start the PHP built-in web server.');
        }

        $server = new self($process, $host, $port, $logFile);
        $server->waitUntilReachable();

        return $server;
    }

    /** Absolute URL for a path on this server: $app->url('/orders?page=2'). */
    public function url(string $path = '/'): string
    {
        return "http://$this->host:$this->port/" . ltrim($path, '/');
    }

    /** Everything the server process logged (requests and PHP errors) — useful when a request fails. */
    public function log(): string
    {
        return (string) @file_get_contents($this->logFile);
    }

    public function stop(): void
    {
        if ($this->process === null) {
            return;
        }
        proc_terminate($this->process);
        proc_close($this->process);
        $this->process = null;
        @unlink($this->logFile);
    }

    public function __destruct()
    {
        $this->stop();
    }

    private function waitUntilReachable(): void
    {
        $deadline = microtime(true) + self::STARTUP_TIMEOUT_IN_SECONDS;
        while (microtime(true) < $deadline) {
            $connection = @fsockopen($this->host, $this->port, timeout: 0.2);
            if ($connection !== false) {
                fclose($connection);
                return;
            }
            if (!proc_get_status($this->process)['running']) {
                break;
            }
            usleep(25_000);
        }

        $log = $this->log();
        $this->stop();
        throw new \RuntimeException("The PHP built-in web server did not start on $this->host:$this->port.\n$log");
    }

    private static function freePort(string $host): int
    {
        $socket = stream_socket_server("tcp://$host:0", $errorCode, $errorMessage);
        if ($socket === false) {
            throw new \RuntimeException("No free port found on $host: $errorMessage");
        }
        $port = (int) substr((string) strrchr((string) stream_socket_get_name($socket, false), ':'), 1);
        fclose($socket);

        return $port;
    }
}
