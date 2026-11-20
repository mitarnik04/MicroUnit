<?php

namespace MicroUnit\Bootstrap;

use MicroUnit\Config\MicroUnitConfig;
use MicroUnit\Helpers\Filesystem;
use MicroUnit\MicroUnit;

/**
 * Finds microunit.config.php (from the working directory upwards), loads and validates it.
 * The lookup is a handful of stat calls, so it is deliberately not cached.
 */
final class ConfigLoader
{
    /** @param string|null $explicitFile a file passed with --config */
    public function load(string $workingDirectory, ?string $explicitFile = null): LoadedConfig
    {
        $file = $explicitFile === null
            ? Filesystem::findUpwards(MicroUnit::CONFIG_FILE_NAME, $workingDirectory)
            : $this->validateExplicit($workingDirectory, $explicitFile);

        if ($file === null) {
            return new LoadedConfig(new MicroUnitConfig(), null, $workingDirectory);
        }

        return new LoadedConfig($this->require($file), $file, dirname($file));
    }

    private function validateExplicit(string $workingDirectory, string $file): string
    {
        $path = Filesystem::resolve($workingDirectory, $file);
        if (!is_file($path)) {
            throw new ConfigurationException("The config file '$file' does not exist.");
        }

        return $path;
    }

    private function require(string $file): MicroUnitConfig
    {
        // Isolated scope: the config file sees no variables of the loader.
        try {
            $config = (static fn(string $configFile): mixed => require $configFile)($file);
        } catch (\Error $error) {
            // A typo in the config (unknown method or named argument) is a configuration error, not a crash.
            throw new ConfigurationException(sprintf('%s: %s (line %d)', $file, $error->getMessage(), $error->getLine()), previous: $error);
        }

        if (!$config instanceof MicroUnitConfig) {
            throw new ConfigurationException(sprintf(
                "%s must return a MicroUnit\\Config\\MicroUnitConfig, but returned %s. Did you forget 'return' or '->build()'?",
                $file,
                get_debug_type($config),
            ));
        }

        return $config;
    }
}
