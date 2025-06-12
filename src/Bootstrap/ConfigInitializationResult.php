<?php

namespace MicroUnit\Bootstrap;

use MicroUnit\Config\MicroUnitConfig;

class ConfigInitializationResult
{
    public function __construct(
        public readonly MicroUnitConfig $config,
        public readonly ?string $configFullPath = null
    ) {}
}
