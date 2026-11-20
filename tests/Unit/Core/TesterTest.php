<?php

use MicroUnit\Assertion\Assert;
use MicroUnit\Core\Test;
use MicroUnit\Core\TestCase;
use MicroUnit\Core\Tester;
use MicroUnit\Setup\TestSetup;

$tester = TestSetup::getTester('Tester');

$tester->define('define_group_names_tests_after_the_data_set_keys', function (): void {
    $sample = new Tester('Math');
    $sample->defineGroup('adds', fn(int $a, int $b, int $sum) => null, [
        'small numbers' => [1, 2, 3],
        'negatives' => [-1, -2, -3],
    ]);

    Assert::exact(['Math::adds_small numbers', 'Math::adds_negatives'], array_map(fn(Test $test) => $test->id, $sample->getTests()));
    Assert::exact([1, 2, 3], $sample->getTests()[0]->args);
    Assert::exact('adds', $sample->getTests()[0]->group);
});

$tester->define('define_group_numbers_unnamed_data_sets_from_one', function (): void {
    $sample = new Tester('Math');
    $sample->defineGroup('doubles', fn(int $in, int $out) => null, [[1, 2], [2, 4]]);

    Assert::exact(['doubles_1', 'doubles_2'], array_map(fn(Test $test) => $test->name, $sample->getTests()));
});

$tester->define('define_group_accepts_generators_and_test_cases', function (): void {
    $sample = new Tester('Math');
    $sample->defineGroup('squares', fn(int $in) => null, (function (): Generator {
        yield 'two' => [2];
        yield new TestCase('three', 3);
    })());

    Assert::exact(['squares_two', 'squares_three'], array_map(fn(Test $test) => $test->name, $sample->getTests()));
    Assert::exact([3], $sample->getTests()[1]->args);
});

$tester->define('duplicate_names_get_unique_ids', function (): void {
    $sample = new Tester('Math');
    $sample->define('same', fn() => null);
    $sample->define('same', fn() => null);

    Assert::exact(['Math::same', 'Math::same#2'], array_map(fn(Test $test) => $test->id, $sample->getTests()));
});
