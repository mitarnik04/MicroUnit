<?php

namespace MicroUnit\Output;

use MicroUnit\Helpers\Filesystem;
use MicroUnit\Output\Format\DetailedResultFormatter;
use MicroUnit\Output\Format\MinimalResultFormatter;
use MicroUnit\Output\Format\TextStyle;

/** Writes the console output (without colours) to a file. The file is written once, after the run. */
final class FileTestWriter extends TextTestWriter
{
    private readonly string $filePath;

    private string $buffer = '';

    /**
     * @param string $filePath absolute, or relative to the current working directory
     * @param bool $detailed use the StringTestWriter format instead of the minimal one
     */
    public function __construct(
        string $filePath = 'test-results.log',
        private readonly bool $clearFileBeforeRun = false,
        bool $detailed = false,
    ) {
        $this->filePath = Filesystem::resolve((string) getcwd(), $filePath);
        parent::__construct($detailed ? new DetailedResultFormatter(TextStyle::plain()) : new MinimalResultFormatter(TextStyle::plain()));
    }

    #[\Override]
    public function writeSummary(int $totalTests, int $successes, int $failures): void
    {
        parent::writeSummary($totalTests, $successes, $failures);
        Filesystem::ensureDirectory(dirname($this->filePath));
        file_put_contents($this->filePath, $this->buffer, $this->clearFileBeforeRun ? 0 : FILE_APPEND);
        $this->buffer = '';
    }

    #[\Override]
    protected function write(string $text): void
    {
        $this->buffer .= $text;
    }
}
