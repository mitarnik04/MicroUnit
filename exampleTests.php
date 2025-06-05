<?php

$tester = getTester("Example");

$tester->define("TestPasses", function () {
    Assert::isTrue(true);
});


$tester->define("TestPasses2", function () {
    Assert::isTrue(true);
});


$tester->define("TestFails", function () {
    Assert::isTrue(false);
});
