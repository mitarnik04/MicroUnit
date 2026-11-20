<?php

namespace MicroUnit\Bootstrap;

/** PSR-4 autoloader for MicroUnit's own classes, so the runner works with or without Composer. */
final class Autoloader
{
    private const string NAMESPACE_PREFIX = 'MicroUnit\\';

    private function __construct() {}

    public static function register(): void
    {
        $sourceDirectory = dirname(__DIR__);

        spl_autoload_register(static function (string $class) use ($sourceDirectory): void {
            if (!str_starts_with($class, self::NAMESPACE_PREFIX)) {
                return;
            }

            $file = $sourceDirectory . '/' . str_replace('\\', '/', substr($class, strlen(self::NAMESPACE_PREFIX))) . '.php';
            if (is_file($file)) {
                require $file;
            }
        });
    }
}
