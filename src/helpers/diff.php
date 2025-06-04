<?php
class Diff
{
    /**
     * Generate a simple line-by-line diff similar to xdiff_string_diff.
     * @return string A unified diff string.
     */
    public static function generate(string $expected, string $actual): string
    {
        $expectedLines = explode("\n", $expected);
        $actualLines = explode("\n", $actual);

        $expectedCount = count($expectedLines);
        $actualCount = count($actualLines);
        $max = $expectedCount > $actualCount ? $expectedCount : $actualCount;

        $result = [];

        for ($i = 0; $i < $max; ++$i) {
            $e = $i < $expectedCount ? $expectedLines[$i] : null;
            $a = $i < $actualCount ? $actualLines[$i] : null;

            if ($e === $a) {
                $result[] = '  ' . $e;
            } else {
                if ($e !== null) {
                    $result[] = '- ' . $e;
                }
                if ($a !== null) {
                    $result[] = '+ ' . $a;
                }
            }
        }

        return implode("\n", $result);
    }
}
