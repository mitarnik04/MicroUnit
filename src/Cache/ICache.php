<?php

namespace MicroUnit\Cache;

interface ICache
{
    function get(string $key, bool $throwOnNotFound = false): mixed;

    function set(string $key, mixed $value, bool $throwOnKeyExists = false): void;

    function hasKey(string $key): bool;
}
