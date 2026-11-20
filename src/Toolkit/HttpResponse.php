<?php

namespace MicroUnit\Toolkit;

/** A response received with Http::get()/post()/…; assert on it with AssertResponse::begin($response). */
final readonly class HttpResponse
{
    /** @param array<string, list<string>> $headers lower-case header name => values */
    public function __construct(
        public int $status,
        public array $headers,
        public string $body,
    ) {}

    /** The first value of a header (case-insensitive), or null. */
    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)][0] ?? null;
    }

    /** The body decoded as JSON (associative arrays). */
    public function json(): mixed
    {
        return json_decode($this->body, true, flags: JSON_THROW_ON_ERROR);
    }
}
