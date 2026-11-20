<?php

namespace MicroUnit\Output;

use MicroUnit\Core\RunSummary;
use MicroUnit\Core\TestResult;
use MicroUnit\Output\Format\IResultFormatter;

/** Base for writers that render results as text; subclasses only decide where the text goes. */
abstract class TextTestWriter implements ITestWriter
{
    public function __construct(
        private readonly IResultFormatter $formatter,
    ) {}

    #[\Override]
    public function writeResult(TestResult $result): void
    {
        $this->write($this->formatter->result($result));
    }

    /** A suite's results in one write: one system call instead of one per test. */
    #[\Override]
    public function writeResults(array $results): void
    {
        $text = '';
        foreach ($results as $result) {
            $text .= $this->formatter->result($result);
        }
        if ($text !== '') {
            $this->write($text);
        }
    }

    #[\Override]
    public function writeSummary(int $totalTests, int $successes, int $failures): void
    {
        $this->write($this->formatter->summary($totalTests, $successes, $failures, RunSummary::elapsedSeconds()));
    }

    #[\Override]
    public function writeSuite(string $suite): void
    {
        $this->write($this->formatter->suite($suite));
    }

    abstract protected function write(string $text): void;
}
