<?php

use MicroUnit\Assertion\Assert;
use MicroUnit\Helpers\Filesystem;
use MicroUnit\Setup\TestSetup;
use MicroUnit\Toolkit\TemporaryDirectory;

$tester = TestSetup::getTester('Filesystem');

$tester->define('relative_to_strips_the_directory_with_forward_slashes', function (): void {
    Assert::exact('tests/CartTest.php', Filesystem::relativeTo('C:\app\tests\CartTest.php', 'C:\app'));
    Assert::exact('tests/CartTest.php', Filesystem::relativeTo('/app/tests/CartTest.php', '/app/'));
});

$tester->define('relative_to_keeps_files_elsewhere_and_ignores_an_empty_directory', function (): void {
    Assert::exact('/lib/Cart.php', Filesystem::relativeTo('/lib/Cart.php', '/app'));
    Assert::exact('/application/Cart.php', Filesystem::relativeTo('/application/Cart.php', '/app'));
    Assert::exact('/app/Cart.php', Filesystem::relativeTo('/app/Cart.php', ''));
});

$tester->fixture('directory', fn() => TemporaryDirectory::create(), cleanup: fn(TemporaryDirectory $directory) => $directory->delete());

$tester->define('write_atomically_replaces_the_file_and_leaves_no_temporary_file', function (TemporaryDirectory $directory): void {
    $file = $directory->write('state.json', 'old');

    Filesystem::writeAtomically($file, 'new');

    Assert::fileContentEquals('new', $file);
    Assert::exact([], glob($directory->path('*.tmp')));
});

$tester->define('write_atomically_fails_loudly_when_the_file_cannot_be_written', function (TemporaryDirectory $directory): void {
    mkdir($directory->path('taken'));

    Assert::throws(fn() => Filesystem::writeAtomically($directory->path('taken'), 'data'), RuntimeException::class, 'Could not');
    Assert::exact([], glob($directory->path('*.tmp')));
});
