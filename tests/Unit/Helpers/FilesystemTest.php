<?php

use MicroUnit\Assertion\Assert;
use MicroUnit\Helpers\Filesystem;
use MicroUnit\Setup\TestSetup;

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
