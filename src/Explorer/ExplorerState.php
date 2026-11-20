<?php

namespace MicroUnit\Explorer;

use MicroUnit\Helpers\Filesystem;

/** The explorer's files in the runtime directory: the latest report and the running server's details. */
final readonly class ExplorerState
{
    public function __construct(
        public string $directory,
        public string $projectDirectory,
    ) {}

    /** @return array<string, mixed>|null */
    public function readReport(): ?array
    {
        return $this->readJson($this->directory . '/report.json');
    }

    /** @param array<string, mixed> $report */
    public function writeReport(array $report): void
    {
        Filesystem::writeAtomically($this->directory . '/report.json', self::encode($report));
    }

    public function reportModifiedAt(): int
    {
        return (int) @filemtime($this->directory . '/report.json');
    }

    /** @return array{port: int, pid: int, token: string, project: string}|null */
    public function readServer(): ?array
    {
        return $this->readJson($this->directory . '/server.json');
    }

    /**
     * The explorer server of this project, if one is really listening. A server.json left behind by
     * a server that died without cleaning up (killed, machine restarted) is deleted.
     *
     * @return array{port: int, pid: int, token: string, project: string}|null
     */
    public function liveServer(): ?array
    {
        $server = $this->readServer();
        if ($server === null || $server['project'] !== $this->projectDirectory) {
            return null;
        }

        $connection = @fsockopen('127.0.0.1', $server['port'], timeout: 0.3);
        if ($connection === false) {
            $this->clearServer();

            return null;
        }
        fclose($connection);

        return $server;
    }

    /** @param array{port: int, pid: int, token: string, project: string} $server */
    public function writeServer(array $server): void
    {
        Filesystem::writeAtomically($this->directory . '/server.json', self::encode($server));
    }

    public function clearServer(): void
    {
        @unlink($this->directory . '/server.json');
    }

    public function serverLogFile(): string
    {
        return $this->directory . '/server.log';
    }

    /** @return array<string, mixed>|null */
    private function readJson(string $file): ?array
    {
        if (!is_file($file)) {
            return null;
        }
        $decoded = json_decode((string) file_get_contents($file), true);

        return is_array($decoded) ? $decoded : null;
    }

    /** @param array<string, mixed> $data */
    public static function encode(array $data): string
    {
        return json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR);
    }
}
