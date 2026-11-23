<?php

use MicroUnit\Assertion\Assert;
use MicroUnit\Assertion\AssertArray;
use MicroUnit\Assertion\AssertSingle;
use MicroUnit\Assertion\AssertString;
use MicroUnit\Core\Test;
use MicroUnit\Core\TestContext;
use MicroUnit\Exceptions\TestFailedException;
use MicroUnit\Setup\TestSetup;
use MicroUnit\Toolkit\TemporaryDirectory;

$tester = TestSetup::getTester('SnapshotAssertions');

/**
 * Runs $assertions as if they were inside the test "Invoices::renders" defined in $directory/InvoiceTest.php,
 * with the environment variables given, then restores the real test context.
 *
 * @param array<string, string> $environment
 */
function inSnapshotTest(TemporaryDirectory $directory, Closure $assertions, array $environment = ['CI' => '']): void
{
    $realTest = TestContext::current();
    $previous = array_map(getenv(...), array_combine(array_keys($environment), array_keys($environment)));
    foreach ($environment as $name => $value) {
        putenv("$name=$value");
    }
    TestContext::enter(new Test('Invoices::renders', 'Invoices', 'renders', fn() => null, [], $directory->path('InvoiceTest.php'), 1));
    try {
        $assertions();
    } finally {
        foreach ($previous as $name => $value) {
            putenv($value === false ? $name : "$name=$value");
        }
        $realTest === null ? TestContext::leave() : TestContext::enter($realTest);
    }
}

$tester->fixture('directory', fn() => TemporaryDirectory::create(), cleanup: fn(TemporaryDirectory $directory) => $directory->delete());

$tester->define('the_first_run_writes_the_snapshot_next_to_the_test_file', function (TemporaryDirectory $directory): void {
    inSnapshotTest($directory, fn() => Assert::matchesSnapshot("Invoice #1\nTotal: 10.00"));

    Assert::fileContentEquals("Invoice #1\nTotal: 10.00", $directory->path('__snapshots__/InvoiceTest/renders.snap'));
});

$tester->define('an_unchanged_value_matches', function (TemporaryDirectory $directory): void {
    inSnapshotTest($directory, fn() => Assert::matchesSnapshot(['total' => 10]));
    inSnapshotTest($directory, fn() => Assert::matchesSnapshot(['total' => 10]));
});

$tester->define('a_changed_value_fails_with_a_diff', function (TemporaryDirectory $directory): void {
    inSnapshotTest($directory, fn() => Assert::matchesSnapshot("a\nb"));

    $failure = Assert::throws(fn() => inSnapshotTest($directory, fn() => Assert::matchesSnapshot("a\nc")), TestFailedException::class, '--update-snapshots')->failure;
    Assert::isTrue($failure->diff?->hasChanges());
});

$tester->define('update_mode_rewrites_the_snapshot', function (TemporaryDirectory $directory): void {
    inSnapshotTest($directory, fn() => Assert::matchesSnapshot('old'));
    inSnapshotTest($directory, fn() => Assert::matchesSnapshot('new'), ['CI' => '', 'MICROUNIT_UPDATE_SNAPSHOTS' => '1']);

    Assert::fileContentEquals('new', $directory->path('__snapshots__/InvoiceTest/renders.snap'));
});

$tester->define('ci_never_creates_missing_snapshots', function (TemporaryDirectory $directory): void {
    Assert::throws(fn() => inSnapshotTest($directory, fn() => Assert::matchesSnapshot('x'), ['CI' => 'true']), TestFailedException::class, 'never created in CI');
    Assert::fileNotExists($directory->path('__snapshots__/InvoiceTest/renders.snap'));
});

$tester->define('several_snapshots_in_one_test_are_numbered_or_named', function (TemporaryDirectory $directory): void {
    inSnapshotTest($directory, function (): void {
        Assert::matchesSnapshot('first');
        Assert::matchesSnapshot('second');
        Assert::matchesSnapshot('header', name: 'invoice header');
    });

    Assert::fileExists($directory->path('__snapshots__/InvoiceTest/renders.snap'));
    Assert::fileExists($directory->path('__snapshots__/InvoiceTest/renders_2.snap'));
    Assert::fileExists($directory->path('__snapshots__/InvoiceTest/renders_invoice_header.snap'));
});

$tester->define('windows_line_endings_in_a_checked_out_snapshot_still_match', function (TemporaryDirectory $directory): void {
    $directory->write('__snapshots__/InvoiceTest/renders.snap', "a\r\nb");

    inSnapshotTest($directory, fn() => Assert::matchesSnapshot("a\nb"));
});

$tester->define('named_snapshots_do_not_shift_the_numbering', function (TemporaryDirectory $directory): void {
    inSnapshotTest($directory, function (): void {
        Assert::matchesSnapshot('header', name: 'invoice header');
        Assert::matchesSnapshot('first');
        Assert::matchesSnapshot('second');
    });

    Assert::fileContentEquals('first', $directory->path('__snapshots__/InvoiceTest/renders.snap'));
    Assert::fileContentEquals('second', $directory->path('__snapshots__/InvoiceTest/renders_2.snap'));
});

$tester->define('the_fluent_classes_compare_with_the_same_snapshots', function (TemporaryDirectory $directory): void {
    inSnapshotTest($directory, function (): void {
        Assert::matchesSnapshot('text', name: 'text');
        Assert::matchesSnapshot(['total' => 10], name: 'array');
        Assert::matchesSnapshot(42, name: 'value');
    });

    inSnapshotTest($directory, function (): void {
        AssertString::begin('text')->matchesSnapshot('text');
        AssertArray::begin(['total' => 10])->matchesSnapshot('array');
        AssertSingle::begin(42)->matchesSnapshot('value');
    });
    Assert::throws(fn() => inSnapshotTest($directory, fn() => AssertString::begin('changed')->matchesSnapshot('text')), TestFailedException::class, '--update-snapshots');
});
