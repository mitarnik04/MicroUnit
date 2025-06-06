<?php
enum DiffLineType
{
    case Same;
    case ExpectedDifferent;
    case AcutalDifferent;
}

class DiffLine
{
    public function __construct(
        public readonly DiffLineType $type,
        public readonly string $line
    ) {}
}

class Diff
{
    /** @param array<DiffLine> $diffLines */
    private function __construct(
        public readonly array $diffLines
    ) {}

    /**
     * Generate a simple line-by-line diff similar to xdiff_string_diff.
     * @return string A unified diff string.
     */
    public static function generate(string $expected, string $actual): Diff
    {
        $expectedLines = explode("\n", $expected);
        $actualLines = explode("\n", $actual);

        $expectedCount = count($expectedLines);
        $actualCount = count($actualLines);
        $max = $expectedCount > $actualCount ? $expectedCount : $actualCount;

        /** @var array<DiffLine> */
        $diffLines = [];

        for ($i = 0; $i < $max; ++$i) {
            $e = trim($expectedLines[$i], "\n\r");
            $a = trim($actualLines[$i], "\n\r");

            if ($e === $a) {
                $diffLines[] = new DiffLine(DiffLineType::Same, $e);
                continue;
            }
            if ($e !== null) {
                $diffLines[] = new DiffLine(DiffLineType::ExpectedDifferent, $e);
            }
            if ($a !== null) {
                $diffLines[] = new DiffLine(DiffLineType::AcutalDifferent, $a);
            }
        }

        return new Diff($diffLines);
    }
}
