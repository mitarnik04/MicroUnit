<?php

namespace MicroUnit\Explorer;

/** @internal a request received by the explorer server */
final readonly class HttpRequest
{
    private const int MAX_HEAD_BYTES = 16_384;

    /** @param array<string, string> $headers lower-case name => value */
    public function __construct(
        public string $method,
        public string $path,
        public array $headers,
        public string $body,
    ) {}

    /**
     * Reads one HTTP/1.1 request from a connection; null when the client sent nothing usable, sent
     * too much, or stopped sending for longer than the timeout.
     *
     * @param resource $connection
     */
    public static function read($connection, int $maxBodyBytes = 1_048_576, float $timeoutInSeconds = 2.0): ?self
    {
        stream_set_timeout($connection, (int) $timeoutInSeconds, (int) (fmod($timeoutInSeconds, 1) * 1_000_000));
        $head = '';
        while (!str_contains($head, "\r\n\r\n")) {
            $chunk = fgets($connection, self::MAX_HEAD_BYTES + 1);
            if ($chunk === false || strlen($head) + strlen($chunk) > self::MAX_HEAD_BYTES) {
                return null;
            }
            $head .= $chunk;
        }

        $lines = explode("\r\n", trim($head));
        if (preg_match('~^([A-Z]+) (\S+) HTTP/1\.[01]$~', array_shift($lines), $match) !== 1) {
            return null;
        }

        $headers = [];
        foreach ($lines as $line) {
            [$name, $value] = array_map('trim', explode(':', $line, 2)) + [1 => ''];
            $headers[strtolower($name)] = $value;
        }

        $length = (int) ($headers['content-length'] ?? 0);
        if ($length > $maxBodyBytes) {
            return null;
        }
        $body = '';
        while (($missingBytes = $length - strlen($body)) > 0) {
            $chunk = fread($connection, $missingBytes);
            if ($chunk === false || $chunk === '') {
                return null; // timed out or closed before the announced body arrived
            }
            $body .= $chunk;
        }

        return new self($match[1], (string) parse_url($match[2], PHP_URL_PATH), $headers, $body);
    }

    public function header(string $name): string
    {
        return $this->headers[strtolower($name)] ?? '';
    }
}
