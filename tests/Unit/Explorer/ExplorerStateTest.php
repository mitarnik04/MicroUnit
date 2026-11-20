<?php

use MicroUnit\Assertion\Assert;
use MicroUnit\Explorer\ExplorerState;
use MicroUnit\Setup\TestSetup;
use MicroUnit\Toolkit\TemporaryDirectory;

$tester = TestSetup::getTester('ExplorerState');

$tester->fixture('directory', fn() => TemporaryDirectory::create(), cleanup: fn(TemporaryDirectory $directory) => $directory->delete());

/** A local TCP port nobody listens on (it was free a moment ago). */
function unusedPort(): int
{
    $socket = stream_socket_server('tcp://127.0.0.1:0');
    $port = (int) substr((string) strrchr((string) stream_socket_get_name($socket, false), ':'), 1);
    fclose($socket);

    return $port;
}

$tester->define('a_server_file_without_a_listening_server_is_stale_and_deleted', function (TemporaryDirectory $directory): void {
    $state = new ExplorerState($directory->path(), '/project');
    $state->writeServer(['port' => unusedPort(), 'pid' => 1, 'token' => 't', 'project' => '/project']);

    Assert::isNull($state->liveServer());
    Assert::isFalse($directory->exists('server.json'));
});

$tester->define('a_listening_server_of_this_project_is_live', function (TemporaryDirectory $directory): void {
    $socket = stream_socket_server('tcp://127.0.0.1:0');
    $port = (int) substr((string) strrchr((string) stream_socket_get_name($socket, false), ':'), 1);
    $state = new ExplorerState($directory->path(), '/project');
    $state->writeServer(['port' => $port, 'pid' => 1, 'token' => 't', 'project' => '/project']);

    $server = $state->liveServer();
    fclose($socket);

    Assert::exact($port, $server['port'] ?? null);
});

$tester->define('the_server_of_another_project_is_not_used_and_not_deleted', function (TemporaryDirectory $directory): void {
    $state = new ExplorerState($directory->path(), '/project');
    $state->writeServer(['port' => unusedPort(), 'pid' => 1, 'token' => 't', 'project' => '/elsewhere']);

    Assert::isNull($state->liveServer());
    Assert::isTrue($directory->exists('server.json'));
});
