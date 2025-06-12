<?php

namespace MicroUnit\Config;

class ConfigProvider
{
    private static MicroUnitConfig $config;

    public static function set(MicroUnitConfig $config): void
    {
        self::$config = $config;
    }

    public static function get(): MicroUnitConfig
    {
        return self::$config;
    }
}
