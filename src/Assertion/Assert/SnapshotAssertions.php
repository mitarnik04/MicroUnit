<?php

namespace MicroUnit\Assertion\Assert;

use MicroUnit\Assertion\NotProvided;
use MicroUnit\Core\TestContext;
use MicroUnit\Helpers\Diff;
use MicroUnit\Helpers\Filesystem;
use MicroUnit\Helpers\ValueExporter;

/** Snapshot testing. Part of Assert. */
trait SnapshotAssertions
{
    /** @var array{int, int} the running test (TestContext::runNumber()) and how many unnamed snapshots it has taken */
    private static array $snapshotCounter = [0, 0];

    /**
     * Compares $actual with the snapshot stored for this test, next to the test file in
     * __snapshots__/<file>/<test>.snap. The first run writes the snapshot (review and commit it);
     * later runs fail on any difference. Strings are stored verbatim, other values exported.
     *
     * In CI a missing snapshot fails instead of being written. After an intended change, run
     * `microunit --update-snapshots` to rewrite the snapshots of the tests that ran.
     *
     * @param string|null $name distinguishes several snapshots in one test (default: numbered)
     */
    public static function matchesSnapshot(mixed $actual, ?string $name = null, string $message = ''): void
    {
        $test = TestContext::current() ?? throw new \LogicException('Assert::matchesSnapshot() only works inside a running test.');
        if ($test->file === null) {
            throw new \LogicException("Cannot store a snapshot for '{$test->id}': the test's file is unknown.");
        }

        $content = str_replace("\r\n", "\n", is_string($actual) ? $actual : ValueExporter::export($actual));
        $path = self::snapshotPath($test->name, $test->file, $name);
        $stored = is_file($path) ? str_replace("\r\n", "\n", (string) file_get_contents($path)) : null;

        if ($stored === $content) {
            TestContext::countAssertions();

            return;
        }
        if (getenv('MICROUNIT_UPDATE_SNAPSHOTS') === '1' || ($stored === null && !self::isContinuousIntegration())) {
            Filesystem::writeAtomically($path, $content);
            TestContext::countAssertions();

            return;
        }

        TestContext::countAssertions();
        self::reject(
            $stored === null
                ? "Failed asserting that the snapshot matches: there is no snapshot at $path (they are never created in CI; run the tests locally and commit the file)."
                : "Failed asserting that the value matches the snapshot $path (run with --update-snapshots if the change is intended).",
            $stored ?? NotProvided::Value,
            $content,
            customMessage: $message,
            diff: $stored === null ? null : Diff::fromLines(explode("\n", $stored), explode("\n", $content)),
        );
    }

    /** Unnamed snapshots are numbered in order; named ones do not take a number. */
    private static function snapshotPath(string $testName, string $testFile, ?string $name): string
    {
        if ($name !== null) {
            $fileName = "{$testName}_$name";
        } else {
            if (self::$snapshotCounter[0] !== TestContext::runNumber()) {
                self::$snapshotCounter = [TestContext::runNumber(), 0];
            }
            $number = ++self::$snapshotCounter[1];
            $fileName = $number === 1 ? $testName : "{$testName}_$number";
        }

        return dirname($testFile) . DIRECTORY_SEPARATOR . '__snapshots__' . DIRECTORY_SEPARATOR . basename($testFile, '.php')
            . DIRECTORY_SEPARATOR . preg_replace('/[^A-Za-z0-9._-]+/', '_', $fileName) . '.snap';
    }

    private static function isContinuousIntegration(): bool
    {
        return (string) getenv('CI') !== '' || (string) getenv('TF_BUILD') !== '';
    }
}
