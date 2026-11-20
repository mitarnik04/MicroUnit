<?php

namespace MicroUnit\Toolkit;

/**
 * A tiny HTTP client for integration tests (no cURL needed). Redirects are not followed, so they
 * can be asserted, and error statuses are returned instead of thrown.
 *
 *     $response = Http::post($app->url('/orders'), json: ['sku' => 'A1']);
 */
final class Http
{
    private const float TIMEOUT_IN_SECONDS = 30.0;

    private function __construct() {}

    /** @param array<string, string> $headers */
    public static function get(string $url, array $headers = []): HttpResponse
    {
        return self::request('GET', $url, headers: $headers);
    }

    /**
     * @param array<mixed>|null $json sent as application/json
     * @param array<string, string> $headers
     */
    public static function post(string $url, ?array $json = null, ?string $body = null, array $headers = []): HttpResponse
    {
        return self::request('POST', $url, $json, $body, $headers);
    }

    /**
     * @param array<mixed>|null $json
     * @param array<string, string> $headers
     */
    public static function put(string $url, ?array $json = null, ?string $body = null, array $headers = []): HttpResponse
    {
        return self::request('PUT', $url, $json, $body, $headers);
    }

    /**
     * @param array<mixed>|null $json
     * @param array<string, string> $headers
     */
    public static function patch(string $url, ?array $json = null, ?string $body = null, array $headers = []): HttpResponse
    {
        return self::request('PATCH', $url, $json, $body, $headers);
    }

    /** @param array<string, string> $headers */
    public static function delete(string $url, array $headers = []): HttpResponse
    {
        return self::request('DELETE', $url, headers: $headers);
    }

    /**
     * @param array<mixed>|null $json
     * @param array<string, string> $headers
     */
    public static function request(string $method, string $url, ?array $json = null, ?string $body = null, array $headers = []): HttpResponse
    {
        if ($json !== null) {
            $body = json_encode($json, JSON_THROW_ON_ERROR);
            $headers += ['Content-Type' => 'application/json', 'Accept' => 'application/json'];
        }

        $headerLines = [];
        foreach ($headers as $name => $value) {
            $headerLines[] = "$name: $value";
        }

        $context = stream_context_create(['http' => [
            'method' => $method,
            'header' => $headerLines,
            'content' => $body ?? '',
            'ignore_errors' => true,
            'follow_location' => 0,
            'timeout' => self::TIMEOUT_IN_SECONDS,
        ]]);

        $responseBody = @file_get_contents($url, false, $context);
        $rawHeaders = http_get_last_response_headers() ?? [];
        if ($responseBody === false && $rawHeaders === []) {
            throw new \RuntimeException("$method $url failed: " . (error_get_last()['message'] ?? 'no response'));
        }

        return self::parse($rawHeaders, (string) $responseBody);
    }

    /** @param list<string> $rawHeaders status line followed by header lines */
    private static function parse(array $rawHeaders, string $body): HttpResponse
    {
        $status = 0;
        $headers = [];
        foreach ($rawHeaders as $line) {
            if (preg_match('~^HTTP/\S+\s+(\d{3})~', $line, $match) === 1) {
                // A new status line starts a new response block (e.g. after "100 Continue").
                $status = (int) $match[1];
                $headers = [];
                continue;
            }
            [$name, $value] = array_map('trim', explode(':', $line, 2)) + [1 => ''];
            $headers[strtolower($name)][] = $value;
        }

        return new HttpResponse($status, $headers, $body);
    }
}
