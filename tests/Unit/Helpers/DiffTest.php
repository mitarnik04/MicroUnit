<?php

use MicroUnit\Assertion\Assert;
use MicroUnit\Helpers\Diff;
use MicroUnit\Helpers\DiffLine;
use MicroUnit\Setup\TestSetup;

$tester = TestSetup::getTester('Diff');

/** The lines changesWithContext() keeps, as text: "-"/"+"/" " plus the line, "…" for a folded run. @return list<string> */
function visibleLines(Diff $diff, int $context): array
{
    return array_map(static fn(?DiffLine $line): string => $line === null ? '…' : match ($line->type->value) {
        'same' => " $line->line",
        'expected' => "-$line->line",
        'actual' => "+$line->line",
    }, $diff->changesWithContext($context));
}

$tester->define('changes_keep_their_context_and_unchanged_runs_before_them_are_folded', function (): void {
    $expected = ['a', 'b', 'c', 'd', 'e', 'f', 'g', 'h'];
    $actual = ['a', 'b', 'c', 'D', 'e', 'f', 'g', 'h'];

    // Nothing marks the end: the last change is followed by its context only.
    Assert::exact(['…', ' c', '-d', '+D', ' e'], visibleLines(Diff::fromLines($expected, $actual), 1));
});

$tester->define('changes_close_to_each_other_share_their_context', function (): void {
    $diff = Diff::fromLines(['a', 'b', 'c', 'd', 'e'], ['A', 'b', 'c', 'd', 'E']);

    Assert::exact(['-a', '+A', ' b', ' c', ' d', '-e', '+E'], visibleLines($diff, 2));
});

$tester->define('an_unchanged_diff_shows_nothing', function (): void {
    Assert::exact([], Diff::fromLines(['a', 'b'], ['a', 'b'])->changesWithContext(3));
});

$tester->define('a_large_diff_is_folded_in_linear_time', function (): void {
    $expected = array_map(static fn(int $i): string => "line $i", range(1, 50_000));
    $actual = array_map(static fn(string $line): string => str_ends_with($line, '0') ? strtoupper($line) : $line, $expected);
    $diff = Diff::fromLines($expected, $actual);

    $startedAt = hrtime(true);
    $visible = $diff->changesWithContext(3);

    Assert::isLessThan(1.0, (hrtime(true) - $startedAt) / 1e9); // quadratic folding took minutes here
    Assert::isLessThan(count($diff->lines), count($visible));
});
