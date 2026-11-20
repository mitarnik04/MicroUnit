<?php

use MicroUnit\Assertion\Assert;
use MicroUnit\Coverage\CoverageData;
use MicroUnit\Setup\TestSetup;

$tester = TestSetup::getTester('CoverageData');

$tester->fixture('data', fn() => new CoverageData([
    '/app/Order.php' => [
        'lines' => [3 => 1, 4 => 0, 8 => 2, 9 => 2],
        'functions' => [
            ['name' => 'Order::cancel', 'startLine' => 2, 'endLine' => 5],
            ['name' => 'Order::total', 'startLine' => 7, 'endLine' => 10],
            ['name' => 'Order::abstractHook', 'startLine' => 12, 'endLine' => 12],
        ],
    ],
    '/app/Line.php' => ['lines' => [5 => 1], 'functions' => []],
]));

$tester->define('functions_are_covered_when_all_their_lines_ran', function (CoverageData $data): void {
    $functions = $data->functionsOf('/app/Order.php');

    Assert::exact(['Order::cancel', 'Order::total'], array_column($functions, 'name'));
    Assert::exact([false, true], array_column($functions, 'isCovered'));
    Assert::exact([0, 2], array_column($functions, 'hits'));
});

$tester->define('totals_add_up_every_file', function (CoverageData $data): void {
    Assert::exact(['executableLines' => 4, 'coveredLines' => 3, 'functions' => 2, 'coveredFunctions' => 1], $data->fileTotals('/app/Order.php'));
    Assert::exact(['executableLines' => 5, 'coveredLines' => 4, 'functions' => 2, 'coveredFunctions' => 1], $data->totals());
});

$tester->define('merge_sums_the_hits_of_both_runs', function (CoverageData $data): void {
    $merged = $data->merge(new CoverageData(['/app/Order.php' => ['lines' => [4 => 1], 'functions' => []]]));

    Assert::exact([3 => 1, 4 => 1, 8 => 2, 9 => 2], $merged->files['/app/Order.php']['lines']);
    Assert::exact(4, $merged->fileTotals('/app/Order.php')['coveredLines']);
});
