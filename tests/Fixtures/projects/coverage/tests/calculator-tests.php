<?php

use App\Calculator;
use MicroUnit\Assertion\Assert;
use MicroUnit\Setup\TestSetup;

require_once __DIR__ . '/../src/Calculator.php';

$tester = TestSetup::getTester('Calculator');

$tester->define('adds_two_numbers', function (): void {
    Assert::equals(5, new Calculator()->add(2, 3));
});

$tester->define('divides_two_numbers', function (): void {
    Assert::equals(2.5, new Calculator()->divide(5, 2));
});
