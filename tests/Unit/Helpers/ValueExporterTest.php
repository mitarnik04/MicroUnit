<?php

use MicroUnit\Assertion\Assert;
use MicroUnit\Helpers\ValueExporter;
use MicroUnit\Setup\TestSetup;

$tester = TestSetup::getTester('ValueExporter');

$tester->define('scalars_carry_their_type', function (): void {
    Assert::exact("'a' (string)", ValueExporter::export('a'));
    Assert::exact('3 (int)', ValueExporter::export(3));
    Assert::exact('true (bool)', ValueExporter::export(true));
    Assert::exact('null', ValueExporter::export(null));
});

$tester->define('arrays_and_objects_are_multi_line', function (): void {
    $point = new stdClass();
    $point->x = 1;

    Assert::exact("[\n    'point' => stdClass {\n        x: 1 (int),\n    },\n]", ValueExporter::export(['point' => $point]));
});

$tester->define('recursion_is_marked', function (): void {
    $node = new stdClass();
    $node->self = $node;

    Assert::stringContains('*RECURSION*', ValueExporter::export($node));
});

$tester->define('a_length_budget_stops_early_and_marks_what_was_left_out', function (): void {
    // One object referenced many times: exported in full, the text grows with every reference.
    $leaf = new stdClass();
    $leaf->payload = str_repeat('x', 100);

    $exported = ValueExporter::export(array_fill(0, 10_000, $leaf), 1_000);

    Assert::isLessThan(2_000, strlen($exported));
    Assert::stringEndsWith("    …\n]", $exported);
});

$tester->define('values_within_the_budget_are_complete', function (): void {
    Assert::exact(ValueExporter::export([1, 2, 3]), ValueExporter::export([1, 2, 3], 1_000));
});

$tester->define('a_long_string_is_cut_at_the_length_budget_and_its_length_named', function (): void {
    $exported = ValueExporter::export(str_repeat('x', 100_000), 1_500);

    Assert::exact("'" . str_repeat('x', 1_500) . "'… (string, 100000 bytes)", $exported);
});

$tester->define('a_cut_string_never_ends_inside_a_utf8_character', function (): void {
    $exported = ValueExporter::export('aé' . str_repeat('x', 10), 2);

    Assert::exact("'a'… (string, 13 bytes)", $exported);
});

$tester->define('long_strings_inside_arrays_share_the_budget', function (): void {
    $big = str_repeat('x', 100_000);

    Assert::isLessThan(2_000, strlen(ValueExporter::export([$big, $big, $big], 1_500)));
});
