<?php

use MicroUnit\Assertion\Assert;
use MicroUnit\Helpers\PhpProcess;
use MicroUnit\Setup\TestSetup;
use MicroUnit\Toolkit\TemporaryDirectory;

$tester = TestSetup::getTester('ExplorerServer');

/**
 * A running explorer server for a small project: [project, port, token, process].
 *
 * @return array{TemporaryDirectory, int, string, resource}
 */
function startExplorerServer(): array
{
    $project = TemporaryDirectory::create('microunit-explorer');
    $project->write('microunit.config.php', "<?php\nreturn MicroUnit\Config\MicroUnitConfigBuilder::create()->withTestDir('tests')->build();\n");
    $project->write('tests/example-tests.php', "<?php\nfwrite(STDERR, 'written to stderr' . PHP_EOL);\n\$tester = MicroUnit\Setup\TestSetup::getTester('Example');\n\$tester->define('passes', fn() => MicroUnit\Assertion\Assert::isTrue(true));\n");
    $project->write('coverage/index.html', '<h1>Coverage</h1>');
    $project->write('.microunit/explorer/report.json', json_encode(['coverage' => ['htmlReport' => $project->path('coverage/index.html')]]));

    $socket = stream_socket_server('tcp://127.0.0.1:0');
    $port = (int) substr((string) strrchr((string) stream_socket_get_name($socket, false), ':'), 1);
    fclose($socket);
    $null = PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null';
    $process = proc_open(
        [...PhpProcess::command(dirname(__DIR__, 3) . '/bin/microunit'), '--explorer-server', "--port=$port"],
        [0 => ['file', $null, 'r'], 1 => ['file', $null, 'w'], 2 => ['file', $null, 'w']],
        $pipes,
        $project->root,
    );
    for ($i = 0; $i < 100 && !$project->exists('.microunit/explorer/server.json'); $i++) {
        usleep(50_000);
    }
    $server = json_decode($project->read('.microunit/explorer/server.json'), true);

    return [$project, $port, $server['token'], $process];
}

/** @return array{int, string} status and body of one request to the server */
function explorerRequest(int $port, string $method, string $path, string $body = '', array $headers = []): array
{
    $connection = stream_socket_client("tcp://127.0.0.1:$port", timeout: 5);
    $head = "$method $path HTTP/1.1\r\nHost: 127.0.0.1:$port\r\nContent-Length: " . strlen($body) . "\r\n";
    foreach ($headers as $name => $value) {
        $head .= "$name: $value\r\n";
    }
    fwrite($connection, "$head\r\n$body");
    $response = (string) stream_get_contents($connection);
    fclose($connection);
    [$responseHead, $responseBody] = explode("\r\n\r\n", $response, 2) + [1 => ''];

    return [(int) explode(' ', $responseHead)[1], $responseBody];
}

$tester->fixture('server', fn(): array => startExplorerServer(), cleanup: static function (array $server): void {
    proc_terminate($server[3]);
    proc_close($server[3]);
    $server[0]->delete();
}, shared: true);

$tester->define('a_path_with_a_nul_byte_does_not_stop_the_server', function (array $server): void {
    [, $port] = $server;

    Assert::exact(404, explorerRequest($port, 'GET', '/coverage/%00')[0]);
    Assert::exact([200, '<h1>Coverage</h1>'], explorerRequest($port, 'GET', '/coverage/index.html'));
});

$tester->define('requests_naming_another_host_are_refused', function (array $server): void {
    [, $port] = $server;
    $connection = stream_socket_client("tcp://127.0.0.1:$port", timeout: 5);
    fwrite($connection, "GET /api/state HTTP/1.1\r\nHost: attacker.example:$port\r\n\r\n");

    Assert::stringStartsWith('HTTP/1.1 403 Forbidden', (string) stream_get_contents($connection));
});

$tester->define('a_run_needs_the_token_and_logs_output_and_errors', function (array $server): void {
    [$project, $port, $token] = $server;

    Assert::exact(403, explorerRequest($port, 'POST', '/api/run', '{}')[0]);
    Assert::exact(202, explorerRequest($port, 'POST', '/api/run', '{}', ['X-MicroUnit-Token' => $token])[0]);
    for ($i = 0; $i < 200 && json_decode(explorerRequest($port, 'GET', '/api/state')[1], true)['isRunning']; $i++) {
        usleep(50_000);
    }

    $log = $project->read('.microunit/explorer/last-run.log');
    Assert::stringContains('written to stderr', $log);
    Assert::matchesRegex('~1/1 passed|"passed":1~', $log); // human or agent output, as the outer run
});
