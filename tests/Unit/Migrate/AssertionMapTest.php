<?php

use MicroUnit\Assertion\Assert;
use MicroUnit\Migrate\AssertionMap;
use MicroUnit\Setup\TestSetup;

$tester = TestSetup::getTester('AssertionMap');

$tester->define('names_are_matched_in_any_case', function (): void {
    Assert::exact('Assert::exact(1, $x)', AssertionMap::translate('ASSERTSAME', ['1', '$x']));
    Assert::exact('Assert::isFalse(is_finite($x))', AssertionMap::translate('assertinfinite', ['$x']));
    Assert::isTrue(AssertionMap::isAssertion('assertNotCount'));
    Assert::isFalse(AssertionMap::isAssertion('assertValidOrder'));
});

$tester->define('named_arguments_are_written_in_order_without_their_names', function (): void {
    Assert::exact("Assert::exact(1, \$x, message: 'm')", AssertionMap::translate('assertSame', ['actual: $x', "message: 'm'", 'expected: 1']));
    Assert::exact('Assert::instanceOf(Cart::class, $x)', AssertionMap::translate('assertInstanceOf', ['Cart::class', 'actual: $x']));
    Assert::exact("Assert::isFalse(is_int(\$x), message: 'm')", AssertionMap::translate('assertIsNotInt', ['actual: $x', "message: 'm'"]));
});

$tester->define('a_message_to_assert_contains_does_not_become_the_strict_flag', function (): void {
    Assert::exact("Assert::contains(1, \$list, message: 'm')", AssertionMap::translate('assertContains', ['1', '$list', "'m'"]));
});
