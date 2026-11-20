<?php

namespace MicroUnit\Output\Format;

use MicroUnit\Core\TestResult;
use MicroUnit\Helpers\Filesystem;

/** One line per test; failures are followed by an indented explanation. */
final readonly class MinimalResultFormatter implements IResultFormatter
{
    private const string INDENT = '    ';

    private FailureFormatter $failureFormatter;

    public function __construct(
        private TextStyle $style,
    ) {
        $this->failureFormatter = new FailureFormatter($style);
    }

    #[\Override]
    public function suite(string $suite): string
    {
        return PHP_EOL . $this->style->bold("-- $suite --") . PHP_EOL;
    }

    #[\Override]
    public function result(TestResult $result): string
    {
        $time = $this->style->dim('(' . Duration::format($result->time) . ')');
        if ($result->isSkipped()) {
            return $this->style->yellow('[-]') . " {$result->testName} " . $this->style->dim('skipped: ' . $result->skipReason) . PHP_EOL;
        }
        if ($result->isSuccess) {
            $risky = $result->isRisky() ? ' ' . $this->style->yellow('no assertions') : '';

            return $this->style->green('[✔]') . " {$result->testName} $time$risky" . PHP_EOL;
        }

        $lines = $this->failureFormatter->format($result);
        // Errors name their location already; assertion failures get the line of the failed assertion.
        $line = $result->failureLine ?? $result->line;
        if ($result->assertionFailure !== null && $result->file !== null && $line !== null) {
            // Relative to the working directory, which is where the user runs MicroUnit from.
            $lines[] = $this->style->dim('at ' . Filesystem::relativeTo($result->file, (string) getcwd()) . ':' . $line);
        }
        if (trim($result->output) !== '') {
            $lines[] = $this->style->dim('Output:');
            foreach (explode("\n", rtrim($result->output)) as $outputLine) {
                $lines[] = '  ' . $outputLine;
            }
        }
        $details = array_map(static fn(string $text): string => self::INDENT . $text, $lines);

        return $this->style->red('[✖]') . " {$result->testName} $time" . PHP_EOL
            . implode(PHP_EOL, $details) . PHP_EOL . PHP_EOL;
    }

    #[\Override]
    public function summary(int $totalTests, int $successes, int $failures, float $timeInSeconds): string
    {
        $skipped = $totalTests - $successes - $failures;
        $counts = "Summary: $successes/$totalTests passed, $failures failed" . ($skipped > 0 ? ", $skipped skipped" : '');
        $line = $failures > 0 ? $this->style->red($counts) : $this->style->green($counts);

        return PHP_EOL . $line . $this->style->dim(' in ' . Duration::format($timeInSeconds)) . PHP_EOL;
    }
}
