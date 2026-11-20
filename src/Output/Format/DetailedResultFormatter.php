<?php

namespace MicroUnit\Output\Format;

use MicroUnit\Core\TestResult;
use MicroUnit\Helpers\ValueExporter;

/** A labelled block per test with return value, captured output and stack traces. */
final readonly class DetailedResultFormatter implements IResultFormatter
{
    private const int WIDTH = 80;

    private FailureFormatter $failureFormatter;

    public function __construct(
        private TextStyle $style,
    ) {
        $this->failureFormatter = new FailureFormatter($style);
    }

    #[\Override]
    public function suite(string $suite): string
    {
        $rule = str_repeat('=', self::WIDTH);

        return $rule . PHP_EOL . $this->style->bold(" Test Suite: $suite") . PHP_EOL . $rule . PHP_EOL;
    }

    #[\Override]
    public function result(TestResult $result): string
    {
        $status = match (true) {
            $result->isSkipped() => $this->style->yellow('Skipped') . ' ' . $this->style->dim((string) $result->skipReason),
            $result->isSuccess => $this->style->green('Passed'),
            default => $this->style->red('Failed'),
        };
        $lines = [
            'Test:     ' . $result->testName,
            'Status:   ' . $status,
            'Time:     ' . Duration::format($result->time),
        ];
        if ($result->file !== null) {
            $lines[] = 'Location: ' . $this->style->dim($result->file . ':' . $result->line);
        }
        if ($result->result !== null) {
            $lines[] = 'Result:   ' . ValueExporter::export($result->result, 2_000);
        }
        if (!$result->isSuccess) {
            $lines[] = 'Failure:';
            foreach ($this->failureFormatter->format($result, withTrace: true) as $line) {
                $lines[] = '    ' . $line;
            }
        }
        if ($result->output !== '') {
            $lines[] = 'Output:';
            foreach (explode("\n", rtrim($result->output)) as $line) {
                $lines[] = '    ' . $line;
            }
        }
        $lines[] = str_repeat('-', self::WIDTH);

        return implode(PHP_EOL, $lines) . PHP_EOL;
    }

    #[\Override]
    public function summary(int $totalTests, int $successes, int $failures, float $timeInSeconds): string
    {
        $title = ' Test Summary ';
        $side = intdiv(self::WIDTH - strlen($title), 2);

        return PHP_EOL
            . str_repeat('=', $side) . $title . str_repeat('=', self::WIDTH - $side - strlen($title)) . PHP_EOL
            . "Total:     $totalTests" . PHP_EOL
            . 'Succeeded: ' . $this->style->green((string) $successes) . PHP_EOL
            . 'Failed:    ' . ($failures > 0 ? $this->style->red((string) $failures) : '0') . PHP_EOL
            . 'Skipped:   ' . ($totalTests - $successes - $failures) . PHP_EOL
            . 'Time:      ' . Duration::format($timeInSeconds) . PHP_EOL
            . str_repeat('=', self::WIDTH) . PHP_EOL;
    }
}
