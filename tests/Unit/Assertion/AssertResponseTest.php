<?php

use MicroUnit\Assertion\Assert;
use MicroUnit\Assertion\AssertResponse;
use MicroUnit\Exceptions\TestFailedException;
use MicroUnit\Setup\TestSetup;
use MicroUnit\Tests\Fixtures\AssertionCounter;
use MicroUnit\Toolkit\HttpResponse;

$tester = TestSetup::getTester('AssertResponse');

$tester->define('json_checks_can_be_chained', function (): void {
    $response = new HttpResponse(200, ['content-type' => ['application/json']], '{"data":{"items":[{"id":42}]},"total":1}');

    $counted = AssertionCounter::countedBy(fn() => AssertResponse::begin($response)
        ->isOk()
        ->headerIs('Content-Type', 'application/json')
        ->jsonHasPath('data.items.0')
        ->jsonPathEquals('data.items.0.id', 42)
        ->jsonEquals(['total' => 1, 'data' => ['items' => [['id' => 42]]]]));

    Assert::exact(5, $counted);
});

$tester->define('failures_carry_the_start_of_the_body', function (): void {
    $failure = Assert::throws(fn() => AssertResponse::begin(new HttpResponse(500, [], 'Server error'))->statusIs(201), TestFailedException::class)->failure;

    Assert::exact("Failed asserting that the response status is 201.\nResponse body: Server error", $failure->message);
    Assert::exact(201, $failure->expected);
    Assert::exact(500, $failure->actual);
});

$tester->define('a_body_that_is_not_json_fails_the_json_checks', function (): void {
    Assert::throws(fn() => AssertResponse::begin(new HttpResponse(200, [], '<html>'))->jsonPathEquals('id', 1), TestFailedException::class, 'valid JSON');
});

$tester->define('failures_inside_multiple_are_collected_with_the_others', function (): void {
    $response = new HttpResponse(404, [], '{"error":"missing"}');

    $failure = Assert::throws(fn() => Assert::multiple(function () use ($response): void {
        AssertResponse::begin($response)->isOk()->jsonPathEquals('error', 'gone');
        Assert::isTrue(false);
    }), TestFailedException::class)->failure;

    Assert::stringStartsWith('3 assertions failed:', $failure->message);
});
