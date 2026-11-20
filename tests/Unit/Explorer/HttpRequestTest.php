<?php

use MicroUnit\Assertion\Assert;
use MicroUnit\Explorer\HttpRequest;
use MicroUnit\Setup\TestSetup;

$tester = TestSetup::getTester('HttpRequest');

/**
 * The server side of a connection after the client sent $data (and kept the connection open).
 *
 * @return array{resource, resource} server side, client side
 */
function sentRequest(string $data): array
{
    [$connection, $client] = stream_socket_pair(PHP_OS_FAMILY === 'Windows' ? STREAM_PF_INET : STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
    fwrite($client, $data);

    return [$connection, $client];
}

$tester->define('reads_method_path_headers_and_body', function (): void {
    [$connection] = sentRequest("POST /api/run?x=1 HTTP/1.1\r\nHost: 127.0.0.1:9000\r\nContent-Length: 2\r\n\r\n{}");

    $request = HttpRequest::read($connection);

    Assert::exact('POST', $request?->method);
    Assert::exact('/api/run', $request?->path);
    Assert::exact('127.0.0.1:9000', $request?->header('Host'));
    Assert::exact('{}', $request?->body);
});

$tester->define('a_client_that_stops_sending_its_body_is_given_up_on', function (): void {
    [$connection, $client] = sentRequest("POST /api/run HTTP/1.1\r\nContent-Length: 100\r\n\r\n{\"ids\"");
    $start = microtime(true);

    Assert::isNull(HttpRequest::read($connection, timeoutInSeconds: 0.2));
    Assert::isLessThan(2.0, microtime(true) - $start);
    Assert::isTrue(is_resource($client)); // still connected: the timeout ended the read
});

$tester->define('a_head_that_never_ends_is_refused', function (): void {
    [$connection] = sentRequest('GET /' . str_repeat('a', 20_000));

    Assert::isNull(HttpRequest::read($connection, timeoutInSeconds: 0.2));
});

$tester->define('a_body_larger_than_allowed_is_refused', function (): void {
    [$connection] = sentRequest("POST / HTTP/1.1\r\nContent-Length: 5000\r\n\r\n");

    Assert::isNull(HttpRequest::read($connection, maxBodyBytes: 1000, timeoutInSeconds: 0.2));
});
