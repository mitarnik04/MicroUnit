<?php

namespace MicroUnit\Output;

use MicroUnit\Core\TestResult;
use MicroUnit\Explorer\ExplorerReport;
use MicroUnit\Explorer\StaticReport;
use MicroUnit\Helpers\Filesystem;

/**
 * Writes a self-contained HTML test report: verdict, every reject with its reason, the full list of
 * tests. Ideal as a CI artifact for reviewers; for the interactive view use the Test Explorer.
 */
final class HtmlTestWriter implements ITestWriter
{
    private readonly string $filePath;

    /** @var list<TestResult> */
    private array $results = [];

    /** @param string $filePath absolute, or relative to the current working directory */
    public function __construct(string $filePath = 'test-report.html')
    {
        $this->filePath = Filesystem::resolve((string) getcwd(), $filePath);
    }

    #[\Override]
    public function writeResult(TestResult $result): void
    {
        $this->results[] = $result;
    }

    #[\Override]
    public function writeResults(array $results): void
    {
        array_push($this->results, ...$results);
    }

    #[\Override]
    public function writeSummary(int $totalTests, int $successes, int $failures): void
    {
        Filesystem::writeAtomically($this->filePath, StaticReport::render(ExplorerReport::fromResults($this->results, 'vscode')));
    }

    #[\Override]
    public function writeSuite(string $suite): void {}
}
