<?php

namespace MicroUnit\Helpers;

class Diff
{
    /** @param array<DiffLine> $diffLines */
    private function __construct(
        public readonly array $diffLines
    ) {}

    /**
     * Generate a simple line-by-line diff similar to xdiff_string_diff.
     */
    public static function generate(mixed $expected, mixed $actual): Diff
    {
        $expectedLines = explode("\n",  ValueExporter::export($expected));
        $actualLines = explode("\n", ValueExporter::export($actual));

        $max = max(count($expectedLines), count($actualLines));

        /** @var array<DiffLine> */
        $diffLines = [];

        for ($i = 0; $i < $max; ++$i) {
            $e = $expectedLines[$i];
            $a = $actualLines[$i];

            if ($e === $a) {
                $diffLines[] = new DiffLine(DiffLineType::Same, $e);
                continue;
            }
            if ($e !== null) {
                $diffLines[] = new DiffLine(DiffLineType::ExpectedDifferent, $e);
            }
            if ($a !== null) {
                $diffLines[] = new DiffLine(DiffLineType::ActualDifferent, $a);
            }
        }

        return new Diff($diffLines);
    }
}
