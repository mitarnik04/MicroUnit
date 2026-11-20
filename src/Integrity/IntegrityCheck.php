<?php

namespace MicroUnit\Integrity;

/**
 * Finds tests that were weakened compared to a git revision: removed tests, fewer assertions, new
 * skips, changed expected values, removed data sets, assertions that cannot fail. Made for the age
 * of AI coding agents, which sometimes "fix" a red test by weakening it.
 *
 * These are heuristics: a finding asks for a look, it does not prove cheating, and changed
 * production code is not checked. Reads git with the git command line; no dependency.
 */
final readonly class IntegrityCheck
{
    public function __construct(
        private string $projectDirectory,
        private string $revision = 'HEAD',
    ) {}

    public function isAvailable(): bool
    {
        return trim((string) $this->git(['rev-parse', '--is-inside-work-tree'])) === 'true'
            && $this->git(['rev-parse', '--verify', '--quiet', $this->revision . '^{commit}']) !== null;
    }

    /**
     * @param list<string> $testDirectories relative to the project directory
     * @return list<array{test: string, file: string, line: int, finding: string}>
     */
    public function findings(array $testDirectories): array
    {
        // -z: NUL-separated "status\0path\0" pairs, paths unquoted (spaces, non-ASCII).
        $changes = explode("\0", rtrim((string) $this->git(['diff', '-z', '--name-status', '--relative', '--no-renames', $this->revision, '--', ...$testDirectories]), "\0"));
        $findings = [];
        foreach (array_chunk($changes, 2) as $change) {
            [$status, $file] = $change + [1 => ''];
            if (!str_ends_with($file, '.php') || $status === 'A') {
                continue;
            }
            $before = TestSource::parse((string) $this->git(['show', $this->revision . ':./' . $file]));
            $after = TestSource::parse($status === 'D' ? '' : (string) @file_get_contents($this->projectDirectory . '/' . $file));
            foreach ($after->weakenedSince($before) as $name => $testFindings) {
                foreach ($testFindings as $finding) {
                    $findings[] = ['test' => $name, 'file' => $file, 'line' => ($after->tests[$name] ?? $before->tests[$name])['line'], 'finding' => $finding];
                }
            }
        }

        return $findings;
    }

    /** @param list<string> $arguments */
    private function git(array $arguments): ?string
    {
        // stderr is not read: a full stderr pipe would block git while we wait for stdout.
        $nul = PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null';
        $process = proc_open(['git', '-C', $this->projectDirectory, ...$arguments], [1 => ['pipe', 'w'], 2 => ['file', $nul, 'w']], $pipes);
        if ($process === false) {
            return null;
        }
        $output = (string) stream_get_contents($pipes[1]);
        fclose($pipes[1]);

        return proc_close($process) === 0 ? $output : null;
    }
}
