<?php

namespace MicroUnit\Assertion;

use MicroUnit\Core\TestContext;

/**
 * Fluent assertions for HTTP responses — MicroUnit's HttpResponse, PSR-7, Symfony and Laravel:
 *
 *     AssertResponse::begin($response)->statusIs(201)->jsonPathEquals('data.sku', 'A1');
 *
 * Every check counts as one assertion and fails like any Assert method (so Assert::multiple()
 * collects it). Not readonly: the JSON body is decoded once, on first use.
 */
final class AssertResponse
{
    /** The decoded JSON body; NotProvided::Value until json() decoded it */
    private mixed $json = NotProvided::Value;

    private function __construct(
        private readonly ResponseView $response,
    ) {}

    public static function begin(object $response): self
    {
        return new self(ResponseView::of($response));
    }

    public function statusIs(int $expected, string $message = ''): self
    {
        $status = $this->response->status;
        if (!self::passes($status === $expected)) {
            $this->fail("Failed asserting that the response status is $expected.", $expected, $status, $message);
        }

        return $this;
    }

    /** Any 2xx status. */
    public function isOk(string $message = ''): self
    {
        $status = $this->response->status;
        if (!self::passes($status >= 200 && $status < 300)) {
            $this->fail('Failed asserting that the response is successful (2xx).', '2xx', $status, $message);
        }

        return $this;
    }

    public function isNotFound(string $message = ''): self
    {
        return $this->statusIs(404, $message);
    }

    /** A 3xx status, optionally to an exact Location. */
    public function isRedirect(?string $location = null, string $message = ''): self
    {
        $status = $this->response->status;
        if (!self::passes($status >= 300 && $status < 400)) {
            $this->fail('Failed asserting that the response is a redirect (3xx).', '3xx', $status, $message);
        }

        return $location === null ? $this : $this->headerIs('Location', $location, $message);
    }

    public function headerIs(string $name, string $expected, string $message = ''): self
    {
        $actual = $this->response->header($name);
        if (!self::passes($actual === $expected)) {
            $this->fail("Failed asserting that the '$name' header is '$expected'.", $expected, $actual, $message);
        }

        return $this;
    }

    public function headerContains(string $name, string $needle, string $message = ''): self
    {
        $actual = $this->response->header($name);
        if (!self::passes($actual !== null && str_contains($actual, $needle))) {
            $this->fail("Failed asserting that the '$name' header contains '$needle'.", $needle, $actual, $message);
        }

        return $this;
    }

    public function bodyContains(string $needle, string $message = ''): self
    {
        if (!self::passes(str_contains($this->response->body, $needle))) {
            $this->fail('Failed asserting that the response body contains the expected text.', $needle, $this->response->bodyExcerpt(), $message);
        }

        return $this;
    }

    public function bodyEquals(string $expected, string $message = ''): self
    {
        if (!self::passes($this->response->body === $expected)) {
            $this->fail('Failed asserting that the response body equals the expected text.', $expected, $this->response->bodyExcerpt(), $message);
        }

        return $this;
    }

    /** The decoded JSON body equals $expected (loose comparison, key order irrelevant for objects). */
    public function jsonEquals(mixed $expected, string $message = ''): self
    {
        $actual = $this->json();
        if ($actual !== NotProvided::Value && !self::passes($actual == $expected)) {
            $this->fail('Failed asserting that the JSON body equals the expected value.', $expected, $actual, $message);
        }

        return $this;
    }

    /** Dot path into the JSON body: jsonPathEquals('data.items.0.id', 42). */
    public function jsonPathEquals(string $path, mixed $expected, string $message = ''): self
    {
        $json = $this->json();
        if ($json === NotProvided::Value) {
            return $this;
        }
        [$exists, $actual] = self::jsonPath($json, $path);
        if (!self::passes($exists && $actual === $expected)) {
            $this->fail("Failed asserting that JSON path '$path' equals the expected value.", $expected, $exists ? $actual : '(path does not exist)', $message);
        }

        return $this;
    }

    public function jsonHasPath(string $path, string $message = ''): self
    {
        $json = $this->json();
        if ($json !== NotProvided::Value && !self::passes(self::jsonPath($json, $path)[0])) {
            $this->fail("Failed asserting that the JSON body has the path '$path'.", $path, $this->response->bodyExcerpt(), $message);
        }

        return $this;
    }

    /** The decoded body, decoded once; NotProvided::Value (after failing) when the body is not JSON. */
    private function json(): mixed
    {
        if ($this->json !== NotProvided::Value) {
            return $this->json;
        }

        try {
            return $this->json = json_decode($this->response->body, true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            Assert::failWith(new AssertionFailure('Failed asserting that the response body is valid JSON.', actual: $this->response->bodyExcerpt()));

            return NotProvided::Value;
        }
    }

    /** @return array{bool, mixed} [exists, value] */
    private static function jsonPath(mixed $json, string $path): array
    {
        $value = $json;
        foreach (explode('.', $path) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return [false, null];
            }
            $value = $value[$segment];
        }

        return [true, $value];
    }

    /** Counts the assertion and returns $condition. */
    private static function passes(bool $condition): bool
    {
        TestContext::countAssertions();

        return $condition;
    }

    private function fail(string $description, mixed $expected, mixed $actual, string $customMessage): void
    {
        $body = $this->response->bodyExcerpt(200);
        $context = $body === '' ? '' : "\nResponse body: " . $body;
        Assert::failWith(new AssertionFailure($description . $context, $expected, $actual, customMessage: $customMessage));
    }
}
