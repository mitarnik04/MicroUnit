<?php

namespace MicroUnit\Bootstrap;

use MicroUnit\Helpers\Filesystem;

/**
 * The project's Composer autoloader. A config file that needs a project class loads it lazily;
 * otherwise every run loads it right after coverage instrumentation started (before the framework
 * and the test files), so that project code is included as late as possible.
 */
final class ProjectAutoloader
{
    private static ?string $file = null;

    private static bool $isLoaded = false;

    private static ?\Closure $lazyLoader = null;

    private function __construct() {}

    /** Locates vendor/autoload.php above $projectDirectory (or above MicroUnit itself) and arms lazy loading. */
    public static function prepare(string $projectDirectory, string $packageDirectory): void
    {
        self::$file = Filesystem::findUpwards('vendor/autoload.php', $projectDirectory)
            ?? Filesystem::findUpwards('vendor/autoload.php', $packageDirectory);

        if (self::$file === null) {
            return;
        }

        self::$lazyLoader = static function (string $class): void {
            self::load();
            // Composer is registered now; autoloading once more lets it resolve the class (or interface,
            // trait, enum) that triggered loading.
            class_exists($class);
        };
        spl_autoload_register(self::$lazyLoader);
    }

    public static function load(): void
    {
        if (self::$isLoaded || self::$file === null) {
            return;
        }
        self::$isLoaded = true;

        if (self::$lazyLoader !== null) {
            spl_autoload_unregister(self::$lazyLoader);
            self::$lazyLoader = null;
        }

        require_once self::$file;
    }
}
