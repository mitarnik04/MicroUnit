<?php

use MicroUnit\Assertion\Assert;
use MicroUnit\Migrate\TranslationContext;
use MicroUnit\Setup\TestSetup;

$tester = TestSetup::getTester('TranslationContext');

$tester->define('paths_are_written_relative_to_the_migrated_file', function (): void {
    Assert::exact('__DIR__', TranslationContext::pathExpression('/p/tests', '/p/tests'));
    Assert::exact("__DIR__ . '/../tests/bootstrap.php'", TranslationContext::pathExpression('/p/tests-microunit', '/p/tests/bootstrap.php'));
    Assert::exact("__DIR__ . '/../../tests/Unit'", TranslationContext::pathExpression('C:\p\out\Unit', 'C:\p\tests\Unit'));
});

$tester->define('a_path_on_another_drive_stays_absolute', function (): void {
    Assert::exact("'D:/tests/bootstrap.php'", TranslationContext::pathExpression('C:\p\out', 'D:\tests\bootstrap.php'));
});
