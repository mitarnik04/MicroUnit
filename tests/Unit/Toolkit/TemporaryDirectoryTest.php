<?php

use MicroUnit\Assertion\Assert;
use MicroUnit\Assertion\AssertFile;
use MicroUnit\Setup\TestSetup;
use MicroUnit\Toolkit\TemporaryDirectory;

$tester = TestSetup::getTester('TemporaryDirectory');

$tester->fixture('directory', fn() => TemporaryDirectory::create(), cleanup: fn(TemporaryDirectory $directory) => $directory->delete());

$tester->define('delete_removes_nested_files_and_directories', function (TemporaryDirectory $directory): void {
    $directory->write('a.txt', 'a');
    $directory->write('nested/deeper/b.txt', 'b');

    $directory->delete();

    AssertFile::begin($directory->root)->notExists();
});

$tester->define('delete_removes_a_link_whose_target_is_gone', function (TemporaryDirectory $directory): void {
    Assert::skipIf(!@symlink($directory->path('gone.txt'), $directory->path('link')), 'Creating symlinks is not allowed here.');

    $directory->delete();

    AssertFile::begin($directory->root)->notExists();
});

$tester->define('delete_removes_a_link_to_a_directory_but_not_what_it_points_to', function (TemporaryDirectory $directory): void {
    $elsewhere = TemporaryDirectory::create();
    $kept = $elsewhere->write('kept.txt', 'still here');
    try {
        Assert::skipIf(!@symlink($elsewhere->root, $directory->path('link')), 'Creating symlinks is not allowed here.');

        $directory->delete();

        AssertFile::begin($directory->root)->notExists();
        AssertFile::begin($kept)->exists();
    } finally {
        $elsewhere->delete();
    }
});

$tester->define('delete_removes_a_windows_junction_but_not_what_it_points_to', function (TemporaryDirectory $directory): void {
    Assert::skipIf(PHP_OS_FAMILY !== 'Windows', 'Junctions exist only on Windows.');
    $elsewhere = TemporaryDirectory::create();
    $kept = $elsewhere->write('kept.txt', 'still here');
    try {
        exec('mklink /J ' . escapeshellarg($directory->path('junction')) . ' ' . escapeshellarg($elsewhere->root) . ' 2>NUL', result_code: $code);
        Assert::exact(0, $code, 'Could not create the junction.');

        $directory->delete();

        AssertFile::begin($directory->root)->notExists();
        AssertFile::begin($kept)->exists();
    } finally {
        $elsewhere->delete();
    }
});

$tester->define('deleting_twice_is_harmless', function (TemporaryDirectory $directory): void {
    $directory->delete();
    $directory->delete();

    AssertFile::begin($directory->root)->notExists();
});
