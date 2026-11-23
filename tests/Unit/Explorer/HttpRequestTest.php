<?php

use MicroUnit\Assertion\Assert;
use MicroUnit\Explorer\HttpRequest;
use MicroUnit\Setup\TestSetup;

$tester = TestSetup::getTester('HttpRequest');

/**
 * What the server reads from a client that sent $data in full. In memory rather than through a
 * socket, so a request of any size fits: a UNIX socket pair buffers only 8 KB on macOS.
 *
 * @return resource
 */
function sentRequest(string $data)
{
    $stream = fopen('php://memory', 'r+');
    fwrite($stream, $data);
    rewind($stream);

    return $stream;
}

/**
 * The server side of a connection whose client sent $data and then went quiet without closing it.
 *
 * @return array{resource, resource} server side, client side
 */
function stalledConnection(string $data): array
{
    [$connection, $client] = stream_socket_pair(PHP_OS_FAMILY === 'Windows' ? STREAM_PF_INET : STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
    fwrite($client, $data);

    return [$connection, $client];
}

$tester->define('reads_method_path_headers_and_body', function (): void {
    $request = HttpRequest::read(sentRequest("POST /api/run?x=1 HTTP/1.1\r\nHost: 127.0.0.1:9000\r\nContent-Length: 2\r\n\r\n{}"));

    Assert::exact('POST', $request?->method);
    Assert::exact('/api/run', $request?->path);
    Assert::exact('127.0.0.1:9000', $request?->header('Host'));
    Assert::exact('{}', $request?->body);
});

$tester->define('a_client_that_stops_sending_its_body_is_given_up_on', function (): void {
    [$connection, $client] = stalledConnection("POST /api/run HTTP/1.1\r\nContent-Length: 100\r\n\r\n{\"ids\"");
    $start = microtime(true);

    Assert::isNull(HttpRequest::read($connection, timeoutInSeconds: 0.2));
    Assert::isLessThan(2.0, microtime(true) - $start);
    Assert::isTrue(is_resource($client)); // still connected: the timeout ended the read
});

$tester->define('a_head_larger_than_allowed_is_refused', function (): void {
    Assert::isNull(HttpRequest::read(sentRequest('GET /' . str_repeat('a', 20_000) . " HTTP/1.1\r\n\r\n")));
});

$tester->define('a_body_larger_than_allowed_is_refused', function (): void {
    Assert::isNull(HttpRequest::read(sentRequest("POST / HTTP/1.1\r\nContent-Length: 5000\r\n\r\n" . str_repeat('x', 5000)), maxBodyBytes: 1000));
});
