<?php

namespace MicroUnit\Output;

use MicroUnit\Core\TestResult;
use MicroUnit\Helpers\Filesystem;

/**
 * Emits GitHub Actions workflow commands so failing tests appear as annotations on the
 * failing line in the pull request diff. Added automatically when running on GitHub Actions.
 */
final class GitHubActionsTestWriter implements ITestWriter
{
    #[\Override]
    public function writeResult(TestResult $result): void
    {
        if ($result->isSuccess) {
            return; // passed and skipped tests need no annotation
        }

        $properties = ['title' => $result->suite . ' > ' . $result->testName];
        if ($result->file !== null) {
            $properties = [
                'file' => Filesystem::relativeTo($result->file, (string) getenv('GITHUB_WORKSPACE')),
                'line' => (string) ($result->failureLine ?? $result->line),
                ...$properties,
            ];
        }

        $propertyList = implode(',', array_map(
            static fn(string $key, string $value): string => $key . '=' . self::escapeProperty($value),
            array_keys($properties),
            $properties,
        ));

        echo '::error ' . $propertyList . '::' . self::escapeData($result->errorMsg ?? 'Test failed') . PHP_EOL;
    }

    #[\Override]
    public function writeResults(array $results): void
    {
        foreach ($results as $result) {
            $this->writeResult($result);
        }
    }

    #[\Override]
    public function writeSummary(int $totalTests, int $successes, int $failures): void {}

    #[\Override]
    public function writeSuite(string $suite): void {}

    public static function isRunningOnGitHubActions(): bool
    {
        return getenv('GITHUB_ACTIONS') === 'true';
    }

    private static function escapeData(string $value): string
    {
        return str_replace(['%', "\r", "\n"], ['%25', '%0D', '%0A'], $value);
    }

    private static function escapeProperty(string $value): string
    {
        return str_replace([':', ','], ['%3A', '%2C'], self::escapeData($value));
    }
}
