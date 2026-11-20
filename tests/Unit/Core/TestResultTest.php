<?php

use MicroUnit\Assertion\Assert;
use MicroUnit\Core\Test;
use MicroUnit\Core\TestResult;
use MicroUnit\Helpers\ExportedValue;
use MicroUnit\Setup\TestSetup;

$tester = TestSetup::getTester('TestResult');

$tester->define('for_transport_exports_values_only_as_far_as_any_output_shows_them', function (): void {
    $test = new Test('Cart::returns', 'Cart', 'returns', fn() => null);
    $huge = array_fill(0, 100_000, str_repeat('x', 100));

    $transported = TestResult::passed($test, 0.001, $huge)->forTransport();

    Assert::instanceOf(ExportedValue::class, $transported->result);
    Assert::isLessThan(ExportedValue::MAXIMUM_LENGTH + 1_000, strlen($transported->result->representation));
});
