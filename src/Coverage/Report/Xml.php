<?php

namespace MicroUnit\Coverage\Report;

/** @internal */
final class Xml
{
    private function __construct() {}

    public static function escape(string $text): string
    {
        return htmlspecialchars($text, ENT_XML1 | ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
