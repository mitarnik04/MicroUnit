<?php

use MicroUnit\Assertion\Assert;
use MicroUnit\Assertion\AssertString;
use MicroUnit\Exceptions\TestFailedException;
use MicroUnit\Setup\TestSetup;
use MicroUnit\Tests\Fixtures\AssertionCounter;

$tester = TestSetup::getTester('AssertString');

$tester->define('checks_can_be_chained_and_each_counts', function (): void {
    $counted = AssertionCounter::countedBy(fn() => AssertString::begin('post-42')->notEmpty()->startsWith('post-')->matchesRegex('/^[a-z0-9-]+$/'));

    Assert::exact(3, $counted);
});

$tester->defineGroup('passes_when_the_string_matches', function (Closure $check): void {
    $check();
}, [
    'equals' => fn() => AssertString::begin('a')->equals('a'),
    'exact' => fn() => AssertString::begin('a')->exact('a'),
    'notEquals' => fn() => AssertString::begin('1.0')->notEquals('1'),
    'notExact' => fn() => AssertString::begin('a')->notExact('b'),
    'equalsIgnoringCase' => fn() => AssertString::begin('Hello')->equalsIgnoringCase('hELLO'),
    'equalsIgnoringWhitespace' => fn() => AssertString::begin("<p>\n  Hi\n</p>")->equalsIgnoringWhitespace('<p> Hi </p>'),
    'contains' => fn() => AssertString::begin('order shipped')->contains('ship'),
    'notContains' => fn() => AssertString::begin('order shipped')->notContains('Ship'),
    'containsIgnoringCase' => fn() => AssertString::begin('order shipped')->containsIgnoringCase('SHIP'),
    'startsWith' => fn() => AssertString::begin('INV-1')->startsWith('INV-'),
    'endsWith' => fn() => AssertString::begin('report.csv')->endsWith('.csv'),
    'matchesRegex' => fn() => AssertString::begin('2026-11-21')->matchesRegex('/^\d{4}-\d{2}-\d{2}$/'),
    'matchesFormat' => fn() => AssertString::begin('Took 12 ms')->matchesFormat('Took %d ms'),
    'lengthEquals' => fn() => AssertString::begin('grüß')->lengthEquals(4),
    'empty' => fn() => AssertString::begin('')->empty(),
    'notEmpty' => fn() => AssertString::begin(' ')->notEmpty(),
    'isJson' => fn() => AssertString::begin('{"id":1}')->isJson(),
    'jsonEquals' => fn() => AssertString::begin('{"b":2,"a":1}')->jsonEquals(['a' => 1, 'b' => 2]),
]);

$tester->defineGroup('fails_when_the_string_does_not_match', function (Closure $check): void {
    Assert::throws($check, TestFailedException::class);
}, [
    'equals' => fn() => AssertString::begin('1.0')->equals('1'),
    'exact' => fn() => AssertString::begin('a')->exact('b'),
    'notEquals' => fn() => AssertString::begin('a')->notEquals('a'),
    'notExact' => fn() => AssertString::begin('a')->notExact('a'),
    'equalsIgnoringCase' => fn() => AssertString::begin('Hello')->equalsIgnoringCase('Help'),
    'equalsIgnoringWhitespace' => fn() => AssertString::begin('a b')->equalsIgnoringWhitespace('ab'),
    'contains' => fn() => AssertString::begin('order shipped')->contains('Ship'),
    'notContains' => fn() => AssertString::begin('order shipped')->notContains('ship'),
    'containsIgnoringCase' => fn() => AssertString::begin('order shipped')->containsIgnoringCase('paid'),
    'startsWith' => fn() => AssertString::begin('INV-1')->startsWith('ORD-'),
    'endsWith' => fn() => AssertString::begin('report.csv')->endsWith('.pdf'),
    'matchesRegex' => fn() => AssertString::begin('21.11.2026')->matchesRegex('/^\d{4}-\d{2}-\d{2}$/'),
    'matchesFormat' => fn() => AssertString::begin('Took a while')->matchesFormat('Took %d ms'),
    'lengthEquals' => fn() => AssertString::begin('grüß')->lengthEquals(6),
    'empty' => fn() => AssertString::begin(' ')->empty(),
    'notEmpty' => fn() => AssertString::begin('')->notEmpty(),
    'isJson' => fn() => AssertString::begin('{id:1}')->isJson(),
    'jsonEquals' => fn() => AssertString::begin('[1,2]')->jsonEquals([2, 1]),
]);
