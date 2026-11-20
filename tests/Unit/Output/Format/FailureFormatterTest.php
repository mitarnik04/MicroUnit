<?php

use MicroUnit\Assertion\Assert;
use MicroUnit\Helpers\Diff;
use MicroUnit\Output\Format\FailureFormatter;
use MicroUnit\Output\Format\TextStyle;
use MicroUnit\Setup\TestSetup;

$tester = TestSetup::getTester('FailureFormatter');

$tester->define('a_diff_shows_changes_with_three_lines_of_context_and_folds_the_rest', function (): void {
    $expected = array_map(static fn(int $i): string => "line $i", range(1, 12));
    $actual = $expected;
    $actual[8] = 'changed';

    $lines = new FailureFormatter(TextStyle::plain())->formatDiff(Diff::fromLines($expected, $actual));

    Assert::exact(['--- Expected', '+++ Actual', '@@ @@', '  line 6', '  line 7', '  line 8', '- line 9', '+ changed', '  line 10', '  line 11', '  line 12'], $lines);
});

$tester->define('a_large_diff_is_formatted_quickly', function (): void {
    $expected = array_map(static fn(int $i): string => "line $i", range(1, 20_000));
    $actual = array_map(static fn(string $line): string => str_ends_with($line, '1') ? strtoupper($line) : $line, $expected);
    $diff = Diff::fromLines($expected, $actual);

    $startedAt = hrtime(true);
    new FailureFormatter(TextStyle::plain())->formatDiff($diff);

    Assert::isLessThan(1.0, (hrtime(true) - $startedAt) / 1e9); // the quadratic version needed minutes
});
