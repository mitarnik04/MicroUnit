<?php

namespace MicroUnit\Helpers;

/** Converts file name globs (`*Test.php`, `test-?.php`, `Test[A-Z]*.php`) into anchored regexes. */
final class Glob
{
    private function __construct() {}

    public static function toRegex(string $glob): string
    {
        $regex = '';
        $length = strlen($glob);
        for ($i = 0; $i < $length; $i++) {
            $char = $glob[$i];
            if ($char === '*') {
                $regex .= '.*';
            } elseif ($char === '?') {
                $regex .= '.';
            } elseif ($char === '[' && ($closing = strpos($glob, ']', $i + 2)) !== false) {
                $regex .= self::characterClass(substr($glob, $i + 1, $closing - $i - 1));
                $i = $closing;
            } else {
                $regex .= preg_quote($char, '/');
            }
        }

        return '/^' . $regex . '$/i';
    }

    private static function characterClass(string $body): string
    {
        $isNegated = str_starts_with($body, '!');
        if ($isNegated) {
            $body = substr($body, 1);
        }

        return '[' . ($isNegated ? '^' : '') . str_replace(['\\', ']'], ['\\\\', '\]'], $body) . ']';
    }
}
