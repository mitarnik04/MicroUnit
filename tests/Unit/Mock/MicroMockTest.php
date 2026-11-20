<?php

use MicroUnit\Assertion\Assert;
use MicroUnit\Assertion\AssertMock;
use MicroUnit\Mock\MicroMock;
use MicroUnit\Mock\MockBuilder;
use MicroUnit\Mock\MockException;
use MicroUnit\Setup\TestSetup;
use MicroUnit\Tests\Fixtures\PriceList;

$tester = TestSetup::getTester('MicroMock');

$tester->fixture('prices', fn() => MockBuilder::create(PriceList::class)->build()->newInstance());

$tester->define('of_finds_the_mock_of_an_instance', function (PriceList $prices): void {
    Assert::exact(PriceList::class, MicroMock::of($prices)->getTargetType());
    Assert::throws(fn() => MicroMock::of(new stdClass()), MockException::class, 'not a MicroUnit mock');
});

$tester->define('return_values_can_change_after_build', function (PriceList $prices): void {
    Assert::exact(0, $prices->price('book'));

    MicroMock::of($prices)->returns('price', 25);
    Assert::exact(25, $prices->price('book'));

    MicroMock::of($prices)->returnsSequence('price', 1, 2);
    Assert::exact([1, 2, 0], [$prices->price('a'), $prices->price('b'), $prices->price('c')]);
});

$tester->define('returns_map_picks_the_row_matching_the_arguments', function (PriceList $prices): void {
    MicroMock::of($prices)->returnsMap('price', [['book', 'EUR', 10], ['book', 'USD', 11]]);

    Assert::exact(10, $prices->price('book', 'EUR'));
    Assert::exact(11, $prices->price('book', 'USD'));
    Assert::exact(0, $prices->price('pen', 'EUR'));
});

$tester->define('throws_after_build', function (PriceList $prices): void {
    MicroMock::of($prices)->throws('price', new RuntimeException('offline'));

    Assert::throws(fn() => $prices->price('book'), RuntimeException::class, 'offline');
});

$tester->define('intercept_only_keeps_every_other_method_real', function (): void {
    $prices = MockBuilder::create(PriceList::class)->interceptOnly('price')->returns('price', 7)->build()->newInstance();

    Assert::exact('7 EUR', $prices->formatted('book'));
    AssertMock::begin($prices)->isCalledOnce('formatted')->isCalledOnce('price');
});

$tester->define('methods_with_real_behaviour_cannot_be_configured_after_build', function (): void {
    $prices = MockBuilder::create(PriceList::class)->interceptOnly('price')->build()->newInstance();

    Assert::throws(fn() => MicroMock::of($prices)->returns('formatted', 'x'), MockException::class, 'not intercepted');
});

$tester->define('returns_map_on_the_builder', function (): void {
    $prices = MockBuilder::create(PriceList::class)->returnsMap('price', [['book', 10]])->build()->newInstance();

    Assert::exact(10, $prices->price('book'));
});

$tester->define('protected_methods_named_in_intercept_only_can_be_configured_after_build', function (): void {
    $prices = MockBuilder::create(PriceList::class)->interceptOnly('taxPercent')->build()->newInstance();

    MicroMock::of($prices)->returns('taxPercent', 0);

    Assert::exact(100, $prices->gross('book'));
});

$tester->define('method_names_are_case_insensitive_after_build_too', function (PriceList $prices): void {
    MicroMock::of($prices)->returns('PRICE', 30);

    Assert::exact(30, $prices->price('book'));
});

$tester->define('building_the_same_mock_again_reuses_its_class', function (): void {
    $first = MockBuilder::create(PriceList::class)->returns('price', 1)->build()->newInstance();
    $second = MockBuilder::create(PriceList::class)->returns('price', 2)->build()->newInstance();

    Assert::exact($first::class, $second::class);
    Assert::exact([1, 2], [$first->price('a'), $second->price('a')]);
});

$tester->define('a_definition_that_cannot_work_fails_on_every_build', function (): void {
    $build = fn() => MockBuilder::create(PriceList::class)->returns('missing', 1)->build();

    Assert::throws($build, MockException::class, 'does not exist');
    Assert::throws($build, MockException::class, 'does not exist');
});
