<?php

use MicroUnit\Assertion\Assert;
use MicroUnit\Exceptions\TestFailedException;
use MicroUnit\Setup\TestSetup;

$tester = TestSetup::getTester('StringAssertions');

$tester->define('ignoring_case_compares_multibyte_text', function (): void {
    Assert::equalsIgnoringCase('ÄRGER', 'ärger');
    Assert::stringContainsIgnoringCase('WORLD', 'hello world');
    Assert::throws(fn() => Assert::equalsIgnoringCase('a', 'b'), TestFailedException::class);
});

$tester->define('ignoring_whitespace_collapses_runs_and_trims', function (): void {
    Assert::equalsIgnoringWhitespace('<p>Hello world</p>', "  <p>Hello\n\t  world</p>\n");
    Assert::throws(fn() => Assert::equalsIgnoringWhitespace('a b', 'ab'), TestFailedException::class);
});

$tester->define('matches_format_understands_phpt_placeholders', function (): void {
    Assert::matchesFormat("Saved order %d at %s\n%A", "Saved order 42 at 12:00:01\nanything\nmore");
    Assert::matchesFormat('%i %f %x %c 100%%', '-3 1.5e3 ff X 100%');
    Assert::matchesFormat('a%wb', "a \t b");
    Assert::throws(fn() => Assert::matchesFormat('order %d', 'order x'), TestFailedException::class, 'matches the format');
});

$tester->define('matches_format_treats_other_characters_literally', function (): void {
    Assert::matchesFormat('[%s] (ok)', '[x.y] (ok)');
    Assert::throws(fn() => Assert::matchesFormat('a.c', 'abc'), TestFailedException::class);
});

$tester->define('is_json', function (): void {
    Assert::isJson('{"a":[1,2]}');
    Assert::throws(fn() => Assert::isJson('{a:1}'), TestFailedException::class, 'valid JSON');
});

$tester->define('json_equals_ignores_key_order_and_formatting', function (): void {
    Assert::jsonEquals('{"b": 2, "a": {"y": 1, "x": [1, 2]}}', '{"a":{"x":[1,2],"y":1},"b":2}');
    Assert::jsonEquals(['id' => 1, 'tags' => ['a']], '{"tags":["a"],"id":1}');
});

$tester->define('json_equals_keeps_list_order_and_types', function (): void {
    Assert::throws(fn() => Assert::jsonEquals('[1,2]', '[2,1]'), TestFailedException::class);
    Assert::throws(fn() => Assert::jsonEquals('{"id":1}', '{"id":"1"}'), TestFailedException::class);
});

$tester->define('json_equals_shows_a_line_diff_of_pretty_documents', function (): void {
    $failure = Assert::throws(fn() => Assert::jsonEquals('{"a":1,"b":2}', '{"a":1,"b":3}'), TestFailedException::class)->failure;

    Assert::isTrue($failure->diff?->hasChanges());
});

$tester->define('json_equals_rejects_invalid_actual_json', function (): void {
    Assert::throws(fn() => Assert::jsonEquals('{}', 'nope'), TestFailedException::class, 'valid JSON');
});
