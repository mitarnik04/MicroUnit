<?php

use MicroUnit\Assertion\Assert;
use MicroUnit\Core\Tester;
use MicroUnit\Core\TestFilter;
use MicroUnit\Core\TestPlan;
use MicroUnit\Setup\TestSetup;

$tester = TestSetup::getTester('TestPlan');

/** A standalone suite with $count trivial tests. */
function suiteWithTests(string $name, int $count): Tester
{
    $suite = new Tester($name);
    for ($i = 1; $i <= $count; $i++) {
        $suite->define("test_$i", fn() => null);
    }

    return $suite;
}

/** @return list<string> the ids of the planned tests, in plan order */
function testIdsOf(TestPlan $plan): array
{
    return array_map(fn(array $entry): string => $entry[1]->id, $plan->entries);
}

$tester->define('partition_keeps_every_suite_in_one_worker', function (): void {
    $plan = TestPlan::create([suiteWithTests('A', 5), suiteWithTests('B', 3), suiteWithTests('C', 2)], TestFilter::all());

    $suitesPerWorker = array_map(
        fn(int $worker) => array_values(array_unique(array_map(fn(string $id) => explode('::', $id)[0], testIdsOf($plan->partition($worker, 2))))),
        [0, 1],
    );

    Assert::exact([['A'], ['B', 'C']], $suitesPerWorker);
});

$tester->define('partition_covers_every_test_exactly_once', function (): void {
    $plan = TestPlan::create([suiteWithTests('A', 4), suiteWithTests('B', 4), suiteWithTests('C', 1), suiteWithTests('D', 7)], TestFilter::all());

    $all = array_merge(...array_map(fn(int $worker) => testIdsOf($plan->partition($worker, 3)), [0, 1, 2]));
    sort($all);
    $expected = testIdsOf($plan);
    sort($expected);

    Assert::exact($expected, $all);
});

$tester->define('partition_balances_by_test_count', function (): void {
    $plan = TestPlan::create([suiteWithTests('A', 6), suiteWithTests('B', 3), suiteWithTests('C', 3)], TestFilter::all());

    Assert::exact(6, $plan->partition(0, 2)->count());
    Assert::exact(6, $plan->partition(1, 2)->count());
});

$tester->define('exact_ids_filter_selects_only_those_tests', function (): void {
    $plan = TestPlan::create([suiteWithTests('A', 3)], TestFilter::exactIds(['A::test_1', 'A::test_3', 'Unknown::x']));

    Assert::exact(['A::test_1', 'A::test_3'], testIdsOf($plan));
});

$tester->define('pattern_filter_matches_substrings_case_insensitively_or_regex', function (): void {
    $suites = [suiteWithTests('Mailer', 2), suiteWithTests('Cart', 1)];

    Assert::countEquals(2, testIdsOf(TestPlan::create($suites, TestFilter::fromPattern('mailer'))));
    Assert::exact(['Cart::test_1'], testIdsOf(TestPlan::create($suites, TestFilter::fromPattern('/^Cart::/'))));
});
