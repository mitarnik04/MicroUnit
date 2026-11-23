<?php

use MicroUnit\Assertion\Assert;
use MicroUnit\Assertion\AssertFile;
use MicroUnit\Exceptions\TestFailedException;
use MicroUnit\Setup\TestSetup;
use MicroUnit\Tests\Fixtures\AssertionCounter;
use MicroUnit\Toolkit\TemporaryDirectory;

$tester = TestSetup::getTester('AssertFile');

$tester->fixture('directory', function (): TemporaryDirectory {
    $directory = TemporaryDirectory::create();
    $directory->write('report.csv', "id,total\r\n1,10\r\n");

    return $directory;
}, cleanup: fn(TemporaryDirectory $directory) => $directory->delete());

$tester->define('checks_can_be_chained_and_each_counts', function (TemporaryDirectory $directory): void {
    $counted = AssertionCounter::countedBy(fn() => AssertFile::begin($directory->path('report.csv'))->exists()->contains('1,10')->contentEquals("id,total\n1,10\n"));

    Assert::exact(3, $counted);
});

$tester->define('a_directory_is_recognised', function (TemporaryDirectory $directory): void {
    AssertFile::begin($directory->path())->isDirectory();
});

$tester->define('a_missing_path_passes_not_exists', function (TemporaryDirectory $directory): void {
    AssertFile::begin($directory->path('missing.csv'))->notExists();
});

$tester->defineGroup('fails_when_the_path_does_not_match', function (Closure $check, TemporaryDirectory $directory): void {
    Assert::throws(fn() => $check($directory), TestFailedException::class);
}, [
    'exists' => fn(TemporaryDirectory $directory) => AssertFile::begin($directory->path('missing.csv'))->exists(),
    'notExists' => fn(TemporaryDirectory $directory) => AssertFile::begin($directory->path('report.csv'))->notExists(),
    'isDirectory' => fn(TemporaryDirectory $directory) => AssertFile::begin($directory->path('report.csv'))->isDirectory(),
    'contains' => fn(TemporaryDirectory $directory) => AssertFile::begin($directory->path('report.csv'))->contains('2,20'),
    'contentEquals' => fn(TemporaryDirectory $directory) => AssertFile::begin($directory->path('report.csv'))->contentEquals('id,total'),
]);
