<?php

use MicroUnit\Assertion\Assert;
use MicroUnit\Output\FileTestWriter;
use MicroUnit\Setup\TestSetup;
use MicroUnit\Toolkit\TemporaryDirectory;

$tester = TestSetup::getTester('FileTestWriter');

$tester->fixture('directory', fn() => TemporaryDirectory::create(), cleanup: fn(TemporaryDirectory $directory) => $directory->delete());

$tester->define('creates_the_directory_of_its_file', function (TemporaryDirectory $directory): void {
    $writer = new FileTestWriter($directory->path('reports/nightly/results.log'));

    $writer->writeSummary(0, 0, 0);

    Assert::stringContains('Summary: 0/0 passed', $directory->read('reports/nightly/results.log'));
});
