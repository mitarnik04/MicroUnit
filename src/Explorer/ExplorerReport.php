<?php

namespace MicroUnit\Explorer;

use MicroUnit\Assertion\AssertionFailure;
use MicroUnit\Core\RunSummary;
use MicroUnit\Core\Tester;
use MicroUnit\Core\TestResult;
use MicroUnit\Helpers\ValueExporter;
use MicroUnit\MicroUnit;

/**
 * Builds the JSON document the Test Explorer and the static HTML report render. Every defined test
 * is listed; tests that did not run in a partial run keep their previous result, marked stale.
 */
final class ExplorerReport
{
    /** Values are for reading: a test returning a big object graph must not cost seconds and gigabytes. */
    private const int MAXIMUM_VALUE_LENGTH = 20_000;

    /** The same for printed output and stack traces, which a test can make arbitrarily long. */
    private const int MAXIMUM_TEXT_LENGTH = 100_000;

    /** The fields of a test entry that only a run fills in. */
    private const array NOT_RUN = [
        'status' => 'notrun', 'isStale' => false, 'timeInSeconds' => null, 'failureLine' => null, 'lastRunAt' => null,
        'skipReason' => null, 'failure' => null, 'output' => null, 'returnValue' => null,
    ];

    private function __construct() {}

    /**
     * @param list<Tester> $testers
     * @param array<string, mixed>|null $previous the previous report, merged in for partial runs
     * @param array<string, mixed>|null $coverage coverage summary of this run
     * @return array<string, mixed>
     */
    public static function build(
        array $testers,
        RunSummary $summary,
        ?array $previous,
        bool $isPartialRun,
        ?array $coverage,
        string $editor,
        ?string $framework,
    ): array {
        $resultsById = [];
        foreach ($summary->results as $result) {
            $resultsById[$result->id] = $result;
        }
        $previousById = $isPartialRun ? self::testsById($previous) : [];
        $finishedAt = date(DATE_ATOM);

        $suites = [];
        $counts = ['passed' => 0, 'failed' => 0, 'skipped' => 0, 'notrun' => 0];
        $channel = 0;
        foreach ($testers as $tester) {
            $tests = [];
            foreach ($tester->getTests() as $test) {
                $channel++;
                $entry = isset($resultsById[$test->id])
                    ? self::fromResult($resultsById[$test->id], $finishedAt)
                    : self::notRun($test->id, $tester->suite, $test->name, $test->file, $test->line, $previousById[$test->id] ?? null);
                $entry['channel'] = $channel;
                $entry['group'] = $test->group;
                $counts[$entry['status']] = ($counts[$entry['status']] ?? 0) + 1;
                $tests[] = $entry;
            }
            if ($tests !== []) {
                $suites[] = ['name' => $tester->suite, 'tests' => $tests];
            }
        }

        return self::document($finishedAt, ['total' => $channel, ...$counts], $editor, $framework, $suites, [
            'runId' => bin2hex(random_bytes(6)),
            'isPartialRun' => $isPartialRun,
            'coverage' => $coverage,
        ]);
    }

    /**
     * A report of exactly the results a writer received (no merging, no unrun tests), e.g. for
     * the static HTML report.
     *
     * @param list<TestResult> $results
     * @return array<string, mixed>
     */
    public static function fromResults(array $results, string $editor): array
    {
        $summary = new RunSummary();
        $summary->add($results);
        $finishedAt = date(DATE_ATOM);

        $suites = [];
        $channel = 0;
        foreach ($results as $result) {
            $entry = self::fromResult($result, $finishedAt);
            $entry['channel'] = ++$channel;
            $entry['group'] = null;
            $suites[$result->suite][] = $entry;
        }

        return self::document(
            $finishedAt,
            ['total' => $summary->total, 'passed' => $summary->passed, 'failed' => $summary->failed, 'skipped' => $summary->skipped, 'notrun' => 0],
            $editor,
            null,
            array_map(static fn(string $name, array $tests): array => ['name' => $name, 'tests' => $tests], array_keys($suites), $suites),
        );
    }

    /**
     * @param array<string, int> $summary
     * @param list<array<string, mixed>> $suites
     * @param array<string, mixed> $runDetails what only the explorer's report has
     * @return array<string, mixed>
     */
    private static function document(string $finishedAt, array $summary, string $editor, ?string $framework, array $suites, array $runDetails = []): array
    {
        return [
            'version' => 1,
            ...$runDetails,
            'finishedAt' => $finishedAt,
            'durationSeconds' => RunSummary::elapsedSeconds(),
            'summary' => $summary,
            'editorTemplate' => EditorLink::template($editor),
            'environment' => ['microunit' => MicroUnit::VERSION, 'php' => PHP_VERSION, 'framework' => $framework],
            'suites' => $suites,
        ];
    }

    /** @return array<string, mixed> */
    private static function fromResult(TestResult $result, string $finishedAt): array
    {
        return [
            'id' => $result->id,
            'suite' => $result->suite,
            'name' => $result->testName,
            'status' => $result->status->value,
            'isStale' => false,
            'timeInSeconds' => $result->time,
            'file' => $result->file,
            'line' => $result->line,
            'failureLine' => $result->failureLine,
            'lastRunAt' => $finishedAt,
            'skipReason' => $result->skipReason,
            'failure' => self::failure($result),
            'output' => $result->output === '' ? null : self::shortened($result->output),
            'returnValue' => $result->result === null ? null : ValueExporter::export($result->result, self::MAXIMUM_VALUE_LENGTH),
        ];
    }

    /**
     * @param array<string, mixed>|null $previous
     * @return array<string, mixed>
     */
    private static function notRun(string $id, string $suite, string $name, ?string $file, ?int $line, ?array $previous): array
    {
        if ($previous !== null) {
            // Keep the previous outcome, but refresh where the test now lives.
            return [...$previous, 'isStale' => true, 'file' => $file, 'line' => $line];
        }

        return ['id' => $id, 'suite' => $suite, 'name' => $name, 'file' => $file, 'line' => $line, ...self::NOT_RUN];
    }

    /** @return array<string, mixed>|null */
    private static function failure(TestResult $result): ?array
    {
        if ($result->assertionFailure !== null) {
            return self::assertionFailure($result->assertionFailure);
        }
        if ($result->error !== null) {
            return [
                'kind' => 'error',
                'message' => self::shortened($result->error->message),
                'class' => $result->error->class,
                'file' => $result->error->file,
                'line' => $result->error->line,
                'trace' => self::shortened($result->error->trace),
            ];
        }

        return null;
    }

    /** @return array<string, mixed> */
    private static function assertionFailure(AssertionFailure $failure): array
    {
        return [
            'kind' => 'assertion',
            'message' => self::shortened($failure->message),
            'customMessage' => $failure->customMessage === '' ? null : $failure->customMessage,
            'expected' => $failure->hasExpected() ? ValueExporter::export($failure->expected, self::MAXIMUM_VALUE_LENGTH) : null,
            'actual' => $failure->hasActual() ? ValueExporter::export($failure->actual, self::MAXIMUM_VALUE_LENGTH) : null,
            'diff' => $failure->diff === null ? null : array_map(
                static fn($line): array => [$line->type->value, $line->line],
                $failure->diff->lines,
            ),
        ];
    }

    private static function shortened(string $text): string
    {
        return strlen($text) <= self::MAXIMUM_TEXT_LENGTH
            ? $text
            : substr($text, 0, self::MAXIMUM_TEXT_LENGTH) . "\n… (" . (strlen($text) - self::MAXIMUM_TEXT_LENGTH) . ' more bytes)';
    }

    /**
     * @param array<string, mixed>|null $report
     * @return array<string, array<string, mixed>>
     */
    private static function testsById(?array $report): array
    {
        $tests = [];
        foreach ($report['suites'] ?? [] as $suite) {
            foreach ($suite['tests'] as $test) {
                $tests[$test['id']] = $test;
            }
        }

        return $tests;
    }
}
