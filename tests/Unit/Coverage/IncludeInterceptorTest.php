<?php

use MicroUnit\Assertion\Assert;
use MicroUnit\Coverage\IncludeInterceptor;
use MicroUnit\Setup\TestSetup;
use MicroUnit\Toolkit\TemporaryDirectory;

$tester = TestSetup::getTester('IncludeInterceptor');

/** Runs $checks with the interceptor in place: the one of a --coverage run, or a temporary one. */
function withIncludeInterceptor(Closure $checks): void
{
    $isActive = new ReflectionProperty(IncludeInterceptor::class, 'instrument')->getValue() !== null;
    if (!$isActive) {
        IncludeInterceptor::register(static fn(string $path): ?string => null);
    }
    try {
        $checks();
    } finally {
        if (!$isActive) {
            IncludeInterceptor::unregister();
        }
    }
}

$tester->fixture('directory', fn() => TemporaryDirectory::create(), cleanup: fn(TemporaryDirectory $directory) => $directory->delete());

$tester->define('file_checks_answer_like_php_itself', function (TemporaryDirectory $directory): void {
    $file = $directory->write('a.txt', 'abc');

    withIncludeInterceptor(function () use ($directory, $file): void {
        clearstatcache();
        Assert::isTrue(file_exists($file));
        Assert::isTrue(is_file($file));
        Assert::isTrue(is_dir($directory->root));
        Assert::isFalse(file_exists($directory->path('missing.txt')));
        Assert::exact(3, filesize($file));
        Assert::exact('abc', file_get_contents($file));
    });
});

$tester->define('a_symlink_whose_target_is_gone_is_still_a_link', function (TemporaryDirectory $directory): void {
    $link = $directory->path('link');
    Assert::skipIf(!@symlink($directory->path('gone.txt'), $link), 'Creating symlinks is not allowed here.');

    withIncludeInterceptor(function () use ($link): void {
        clearstatcache();
        Assert::isTrue(is_link($link));
        Assert::isFalse(file_exists($link));
    });
});

$tester->define('a_child_process_can_write_to_a_file_descriptor', function (TemporaryDirectory $directory): void {
    $output = $directory->path('child.out');

    withIncludeInterceptor(function () use ($output): void {
        $process = proc_open(
            [PHP_BINARY, '-r', 'echo "hello";'],
            [0 => ['file', PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null', 'r'], 1 => ['file', $output, 'w'], 2 => ['file', $output . '.err', 'w']],
            $pipes,
        );
        Assert::exact(0, proc_close($process));
    });

    Assert::fileContentEquals('hello', $output);
});
