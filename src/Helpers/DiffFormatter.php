<?php

namespace MicroUnit\Helpers;

class DiffFormatter
{
    public static function toString(Diff $diff): string
    {
        $converted =  '--- Expected' . PHP_EOL;
        $converted .= '+++ Actual' . PHP_EOL;
        $converted .= '@@ @@' . PHP_EOL;
        foreach ($diff->diffLines as $line) {
            $prefix = match ($line->type) {
                DiffLineType::Same => '  ',
                DiffLineType::ExpectedDifferent => '- ',
                DiffLineType::ActualDifferent => '+ ',
            };
            $converted .= $prefix . rtrim($line->line, "\r\n") . PHP_EOL;
        }

        return $converted . PHP_EOL;
    }
}
