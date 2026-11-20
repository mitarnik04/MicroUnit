<?php

use MicroUnit\Assertion\Assert;
use MicroUnit\Exceptions\TestFailedException;
use MicroUnit\Setup\TestSetup;
use MicroUnit\Tests\Fixtures\AssertionCounter;
use MicroUnit\Toolkit\TemporaryDirectory;

$tester = TestSetup::getTester('FileAssertions');

$tester->fixture('directory', fn() => TemporaryDirectory::create(), cleanup: fn(TemporaryDirectory $directory) => $directory->delete());

$tester->define('exists_checks_files_and_directories', function (TemporaryDirectory $directory): void {
    $file = $directory->write('report.txt', 'ok');

    Assert::fileExists($file);
    Assert::directoryExists($directory->root);
    Assert::fileNotExists($directory->path('missing.txt'));
    Assert::throws(fn() => Assert::fileExists($directory->root), TestFailedException::class);
    Assert::throws(fn() => Assert::fileNotExists($file), TestFailedException::class);
});

$tester->define('file_contains_and_content_equals', function (TemporaryDirectory $directory): void {
    $file = $directory->write('out.csv', "id,name\r\n1,Ada\r\n");

    Assert::fileContains('1,Ada', $file);
    Assert::fileContentEquals("id,name\n1,Ada\n", $file);
    Assert::throws(fn() => Assert::fileContentEquals("id,name\n2,Bob\n", $file), TestFailedException::class);
});

$tester->define('reading_a_missing_file_fails_cleanly', function (TemporaryDirectory $directory): void {
    Assert::throws(fn() => Assert::fileContains('x', $directory->path('nope')), TestFailedException::class, 'exists and is readable');
});

$tester->define('file_content_checks_count_as_one_assertion', function (TemporaryDirectory $directory): void {
    $file = $directory->write('report.txt', 'ok');

    Assert::exact(1, AssertionCounter::countedBy(fn() => Assert::fileContains('ok', $file)));
    Assert::exact(1, AssertionCounter::countedBy(fn() => Assert::fileContentEquals('ok', $file)));
});
