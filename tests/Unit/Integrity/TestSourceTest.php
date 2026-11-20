<?php

use MicroUnit\Assertion\Assert;
use MicroUnit\Integrity\TestSource;
use MicroUnit\Setup\TestSetup;

$tester = TestSetup::getTester('TestSource');

$source = <<<'PHP'
    <?php
    $tester = TestSetup::getTester('Cart');

    $tester->define('totals', function (): void {
        Assert::exact(119.0, total());
        Assert::isGreaterThan(100, total());
        AssertMock::begin($mailer)->isCalledOnce('send')->isCalledWith('send', ['a']);
        assertValidOrder($order);
    });

    $tester->define('skipped', function (): void {
        Assert::skip('not today');
        Assert::isTrue(true);
    });

    $tester->defineGroup('rounds', fn(float $in, float $out) => Assert::exact($out, round($in)), [
        'down' => [1.2, 1.0],
        'up' => [1.6, 2.0],
    ]);
    PHP;

$tester->define('reads_tests_with_suite_ids', function () use ($source): void {
    Assert::keysEqual(['Cart::totals', 'Cart::skipped', 'Cart::rounds'], TestSource::parse($source)->tests);
});

$tester->define('counts_static_chained_and_helper_assertions', function () use ($source): void {
    // exact, isGreaterThan, AssertMock::begin, isCalledOnce, isCalledWith, assertValidOrder
    Assert::exact(6, TestSource::parse($source)->tests['Cart::totals']['assertions']);
});

$tester->define('records_expected_values_skips_and_trivial_assertions', function () use ($source): void {
    $tests = TestSource::parse($source)->tests;

    Assert::exact(['119.0'], $tests['Cart::totals']['expected']);
    Assert::exact(1, $tests['Cart::skipped']['skips']);
    Assert::exact(['Assert::isTrue(true)'], $tests['Cart::skipped']['trivial']);
});

$tester->define('counts_literal_data_sets', function () use ($source): void {
    Assert::exact(2, TestSource::parse($source)->tests['Cart::rounds']['dataSets']);
    Assert::isNull(TestSource::parse($source)->tests['Cart::totals']['dataSets']);
});

$tester->define('broken_code_has_no_tests', function (): void {
    Assert::empty(TestSource::parse('<?php $tester->define(')->tests);
});

$tester->define('reports_weakened_tests', function (): void {
    $before = TestSource::parse(<<<'PHP'
        <?php
        $tester = TestSetup::getTester('Cart');
        $tester->define('totals', function (): void {
            Assert::exact(119.0, total());
            Assert::exact(3, count(items()));
        });
        $tester->define('removed', function (): void {
            Assert::isTrue(ok());
        });
        PHP);
    $after = TestSource::parse(<<<'PHP'
        <?php
        $tester = TestSetup::getTester('Cart');
        $tester->define('totals', function (): void {
            Assert::skip('later');
            Assert::exact(120.0, total());
        });
        PHP);

    Assert::exact([
        'Cart::totals' => ['assertions removed (2 before, 1 now)', 'now skipped', 'expected value changed: 119.0 → 120.0'],
        'Cart::removed' => ['test removed'],
    ], $after->weakenedSince($before));
});

$tester->define('an_added_assertion_does_not_look_like_changed_expected_values', function (): void {
    $before = TestSource::parse("<?php\n\$tester = TestSetup::getTester('Cart');\n\$tester->define('totals', function (): void {\n    Assert::exact(1, a());\n    Assert::exact(2, b());\n});");
    $after = TestSource::parse("<?php\n\$tester = TestSetup::getTester('Cart');\n\$tester->define('totals', function (): void {\n    Assert::exact(0, z());\n    Assert::exact(1, a());\n    Assert::exact(2, b());\n});");

    Assert::exact([], $after->weakenedSince($before));
});
