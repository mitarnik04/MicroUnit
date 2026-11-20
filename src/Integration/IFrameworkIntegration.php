<?php

namespace MicroUnit\Integration;

use MicroUnit\Core\ITestLifecycle;

/**
 * Connects MicroUnit to an application framework. Implementations reference framework classes
 * only at runtime, so MicroUnit itself never depends on any framework package.
 */
interface IFrameworkIntegration extends ITestLifecycle
{
    /** Human-readable name shown in the run header, e.g. "Laravel 12.4". */
    public function name(): string;

    /** Called once, after the project's autoloader is available and before test files are loaded. */
    public function boot(string $projectDirectory): void;
}
