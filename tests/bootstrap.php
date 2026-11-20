<?php

// Autoloading for MicroUnit's own test helpers (tests/Support) and fixture classes (tests/Fixtures).
spl_autoload_register(static function (string $class): void {
    $prefix = 'MicroUnit\\Tests\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $file = __DIR__ . '/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($file)) {
        require $file;
    }
});
