<?php

use MicroUnit\Assertion\Assert;
use MicroUnit\Assertion\AssertMock;
use MicroUnit\Exceptions\TestFailedException;
use MicroUnit\Mock\MockBuilder;
use MicroUnit\Setup\TestSetup;
use MicroUnit\Tests\Fixtures\AssertionCounter;
use MicroUnit\Tests\Fixtures\PriceList;

$tester = TestSetup::getTester('AssertMock');

$tester->fixture('prices', fn() => MockBuilder::create(PriceList::class)->build()->newInstance());

$tester->define('every_check_counts_as_one_assertion', function (PriceList $prices): void {
    $prices->price('book');

    Assert::exact(0, AssertionCounter::countedBy(fn() => AssertMock::begin($prices)));
    Assert::exact(2, AssertionCounter::countedBy(fn() => AssertMock::begin($prices)->isCalledOnce('price')->isCalledWith('price', ['book'])));
});

$tester->define('failures_name_the_mock_the_method_and_the_counts', function (PriceList $prices): void {
    $failure = Assert::throws(fn() => AssertMock::begin($prices)->isCalledTimes('price', 2), TestFailedException::class)->failure;

    Assert::exact('Expected PriceList instance: price() to be called exactly 2 time(s), but it was called 0 time(s).', $failure->message);
    Assert::exact('exactly 2 time(s)', $failure->expected);
    Assert::exact(0, $failure->actual);
});

$tester->define('failures_inside_multiple_are_collected_with_the_others', function (PriceList $prices): void {
    $failure = Assert::throws(fn() => Assert::multiple(function () use ($prices): void {
        AssertMock::begin($prices)->isCalled('price');
        Assert::isTrue(false);
    }), TestFailedException::class)->failure;

    Assert::stringStartsWith('2 assertions failed:', $failure->message);
});

$tester->define('a_wrong_call_argument_is_shown_as_a_diff', function (PriceList $prices): void {
    $prices->price('book', 'USD');

    $failure = Assert::throws(fn() => AssertMock::begin($prices)->isCalledWithOnSpecificCall('price', ['book', 'EUR'], 1), TestFailedException::class)->failure;

    Assert::isTrue($failure->diff?->hasChanges());
});
