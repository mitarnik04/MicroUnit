<?php

namespace MicroUnit\Output;

use MicroUnit\Core\TestResult;
use MicroUnit\Helpers\Filesystem;
use MicroUnit\Helpers\ValueExporter;
use MicroUnit\Output\Format\FailureFormatter;
use MicroUnit\Output\Format\TextStyle;

/**
 * JUnit XML report — the format CI systems (GitHub Actions, GitLab, Jenkins, Azure DevOps,
 * CircleCI, ...) read to display test results.
 */
final class JUnitTestWriter implements ITestWriter
{
    private readonly string $filePath;

    private readonly FailureFormatter $failureFormatter;

    /** @var array<string, list<TestResult>> suite => results */
    private array $suites = [];

    private string $currentSuite = '';

    /** @param string $filePath absolute, or relative to the current working directory */
    public function __construct(string $filePath = 'junit.xml')
    {
        $this->filePath = Filesystem::resolve((string) getcwd(), $filePath);
        $this->failureFormatter = new FailureFormatter(TextStyle::plain());
    }

    #[\Override]
    public function writeResult(TestResult $result): void
    {
        $this->suites[$this->currentSuite][] = $result;
    }

    #[\Override]
    public function writeResults(array $results): void
    {
        foreach ($results as $result) {
            $this->writeResult($result);
        }
    }

    #[\Override]
    public function writeSuite(string $suite): void
    {
        $this->currentSuite = $suite;
    }

    #[\Override]
    public function writeSummary(int $totalTests, int $successes, int $failures): void
    {
        $allResults = array_merge(...array_values($this->suites));
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . sprintf('<testsuites name="MicroUnit" %s>', $this->counts($allResults)) . "\n";
        foreach ($this->suites as $suite => $results) {
            $xml .= sprintf('  <testsuite name="%s" %s>', self::escape($suite), $this->counts($results)) . "\n";
            foreach ($results as $result) {
                $xml .= $this->testCase($result);
            }
            $xml .= "  </testsuite>\n";
        }
        $xml .= "</testsuites>\n";

        Filesystem::writeAtomically($this->filePath, $xml);
    }

    /** @param list<TestResult> $results */
    private function counts(array $results): string
    {
        $failures = count(array_filter($results, static fn(TestResult $r): bool => $r->assertionFailure !== null));
        $errors = count(array_filter($results, static fn(TestResult $r): bool => $r->error !== null));
        $skipped = count(array_filter($results, static fn(TestResult $r): bool => $r->isSkipped()));
        $time = array_sum(array_map(static fn(TestResult $r): float => $r->time, $results));

        return sprintf('tests="%d" failures="%d" errors="%d" skipped="%d" time="%.6f"', count($results), $failures, $errors, $skipped, $time);
    }

    private function testCase(TestResult $result): string
    {
        $attributes = sprintf(
            'name="%s" classname="%s" time="%.6f"',
            self::escape($result->testName),
            self::escape($result->suite),
            $result->time,
        );
        if ($result->file !== null) {
            $attributes .= sprintf(' file="%s" line="%d"', self::escape($result->file), $result->failureLine ?? $result->line);
        }

        $children = '';
        if ($result->isSkipped()) {
            $children .= sprintf("      <skipped message=\"%s\"/>\n", self::escape((string) $result->skipReason));
        } elseif ($result->assertionFailure !== null) {
            $details = implode("\n", $this->failureFormatter->format($result));
            $children .= sprintf(
                "      <failure type=\"AssertionFailure\" message=\"%s\">%s</failure>\n",
                self::escape($result->assertionFailure->fullMessage()),
                self::escape($details),
            );
        } elseif ($result->error !== null) {
            $children .= sprintf(
                "      <error type=\"%s\" message=\"%s\">%s</error>\n",
                self::escape($result->error->class),
                self::escape($result->error->message),
                self::escape($result->error->file . ':' . $result->error->line . "\n" . $result->error->trace),
            );
        }
        if ($result->output !== '') {
            $children .= '      <system-out>' . self::escape($result->output) . "</system-out>\n";
        }
        if ($result->result !== null) {
            $children .= '      <properties><property name="returnValue" value="' . self::escape(ValueExporter::export($result->result, 2_000)) . "\"/></properties>\n";
        }

        return $children === ''
            ? "    <testcase $attributes/>\n"
            : "    <testcase $attributes>\n$children    </testcase>\n";
    }

    private static function escape(string $text): string
    {
        // Strip characters XML 1.0 does not allow (e.g. ANSI escape sequences' ESC).
        $text = preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', $text) ?? $text;

        return htmlspecialchars($text, ENT_XML1 | ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
