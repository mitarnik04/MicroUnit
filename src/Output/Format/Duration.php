<?php

namespace MicroUnit\Output\Format;

final class Duration
{
    private function __construct() {}

    public static function format(float $seconds): string
    {
        return match (true) {
            $seconds >= 60 => sprintf('%dm %02ds', intdiv((int) $seconds, 60), (int) $seconds % 60),
            $seconds >= 1 => number_format($seconds, 2) . 's',
            default => number_format($seconds * 1000, 2) . 'ms',
        };
    }
}
