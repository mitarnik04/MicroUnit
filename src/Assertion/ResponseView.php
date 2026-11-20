<?php

namespace MicroUnit\Assertion;

use MicroUnit\Toolkit\HttpResponse;

/**
 * Reads status, body and headers from any common response object without depending on it:
 * MicroUnit's HttpResponse, PSR-7 responses, Symfony responses and Laravel test responses.
 *
 * @internal
 */
final readonly class ResponseView
{
    /** @param \Closure(string): ?string $header */
    private function __construct(
        public int $status,
        public string $body,
        private \Closure $header,
    ) {}

    public static function of(object $response): self
    {
        if ($response instanceof HttpResponse) {
            return new self($response->status, $response->body, $response->header(...));
        }

        // CodeIgniter's TestResponse wraps a PSR-7-like response.
        if (is_a($response, 'CodeIgniter\Test\TestResponse')) {
            return self::of($response->response());
        }

        // Laravel's TestResponse wraps a Symfony response.
        if (isset($response->baseResponse) && is_object($response->baseResponse)) {
            return self::of($response->baseResponse);
        }

        if (method_exists($response, 'getHeaderLine') && method_exists($response, 'getStatusCode')) {
            // PSR-7
            return new self(
                $response->getStatusCode(),
                (string) $response->getBody(),
                static fn(string $name): ?string => $response->hasHeader($name) ? $response->getHeaderLine($name) : null,
            );
        }

        if (method_exists($response, 'getStatusCode') && method_exists($response, 'getContent')) {
            // Symfony HttpFoundation
            return new self(
                $response->getStatusCode(),
                (string) $response->getContent(),
                static fn(string $name): ?string => $response->headers->get($name),
            );
        }

        throw new \InvalidArgumentException(
            'AssertResponse supports MicroUnit\'s HttpResponse, PSR-7, Symfony and Laravel responses; got ' . $response::class . '.',
        );
    }

    public function header(string $name): ?string
    {
        return ($this->header)($name);
    }

    /** A short excerpt of the body for failure messages. */
    public function bodyExcerpt(int $maxLength = 500): string
    {
        $body = trim($this->body);

        return strlen($body) > $maxLength ? substr($body, 0, $maxLength) . '…' : $body;
    }
}
