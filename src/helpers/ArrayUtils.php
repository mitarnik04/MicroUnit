<?php

namespace MicroUnit\Helpers;

class ArrayUtils
{
    /**
     * Check if at least one element in the array satisfies the callback condition.
     *
     * @param array $array The array to check.
     * @param callable(mixed $value, mixed $key): bool $callback to check if element matches. $key = index if it's not an associative array.
     */
    public static function some(array $array, callable $callback): bool
    {
        foreach ($array as $key => $value) {
            if ($callback($value, $key)) {
                return true;
            }
        }
        return false;
    }
}
