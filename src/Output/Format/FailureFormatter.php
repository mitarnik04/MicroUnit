<?php

namespace MicroUnit\Output\Format;

use MicroUnit\Assertion\AssertionFailure;
use MicroUnit\Core\TestError;
use MicroUnit\Core\TestResult;
use MicroUnit\Helpers\Diff;
use MicroUnit\Helpers\DiffLineType;
use MicroUnit\Helpers\ValueExporter;

/** Renders why a test failed: assertion details with diff, or the unexpected error. Shared by all text writers. */
final readonly class FailureFormatter
{
    /** Unchanged diff lines kept around each change; longer unchanged runs are folded. */
    private const int DIFF_CONTEXT_LINES = 3;

    public function __construct(
        private TextStyle $style,
    ) {}

    /** @return list<string> */
    public function format(TestResult $result, bool $withTrace = false): array
    {
        return match (true) {
            $result->assertionFailure !== null => $this->formatAssertion($result->assertionFailure),
            $result->error !== null => $this->formatError($result->error, $withTrace),
            default => [$this->style->red('Unknown failure')],
        };
    }

    /** @return list<string> */
    public function formatAssertion(AssertionFailure $failure): array
    {
        $lines = [];
        if ($failure->customMessage !== '') {
            foreach (explode("\n", $failure->customMessage) as $line) {
                $lines[] = $this->style->bold($line);
            }
        }
        foreach (explode("\n", $failure->message) as $line) {
            $lines[] = $this->style->red($line);
        }

        if ($failure->diff !== null && count($failure->diff->lines) > 2) {
            return [...$lines, ...$this->formatDiff($failure->diff)];
        }
        if ($failure->hasExpected()) {
            $lines = [...$lines, ...$this->labelled('Expected', ValueExporter::export($failure->expected, 20_000))];
        }
        if ($failure->hasActual()) {
            $lines = [...$lines, ...$this->labelled('Actual  ', ValueExporter::export($failure->actual, 20_000))];
        }

        return $lines;
    }

    /** @return list<string> */
    public function formatError(TestError $error, bool $withTrace): array
    {
        $lines = [
            ...array_map($this->style->red(...), explode("\n", $error->class . ': ' . $error->message)),
            $this->style->dim('at ' . $error->file . ':' . $error->line),
        ];

        return $withTrace ? [...$lines, ...explode("\n", $error->trace)] : $lines;
    }

    /** @return list<string> */
    public function formatDiff(Diff $diff): array
    {
        $lines = [$this->style->red('--- Expected'), $this->style->green('+++ Actual')];
        foreach ($diff->changesWithContext(self::DIFF_CONTEXT_LINES) as $line) {
            $lines[] = match ($line?->type) {
                null => $this->style->cyan('@@ @@'),
                DiffLineType::Same => '  ' . $line->line,
                DiffLineType::ExpectedOnly => $this->style->red('- ' . $line->line),
                DiffLineType::ActualOnly => $this->style->green('+ ' . $line->line),
            };
        }

        return $lines;
    }

    /** @return list<string> */
    private function labelled(string $label, string $value): array
    {
        $valueLines = explode("\n", $value);
        $indent = str_repeat(' ', strlen($label) + 2);
        $lines = [$label . ': ' . array_shift($valueLines)];
        foreach ($valueLines as $line) {
            $lines[] = $indent . $line;
        }

        return $lines;
    }
}
