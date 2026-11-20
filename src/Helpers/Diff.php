<?php

namespace MicroUnit\Helpers;

/** Line diff between the exported forms of two values, based on the longest common subsequence. */
final readonly class Diff
{
    /** Above this many LCS cells the diff degrades to a positional comparison to bound memory. */
    private const int MAX_LCS_CELLS = 1_000_000;

    /** @param list<DiffLine> $lines */
    private function __construct(
        public array $lines,
    ) {}

    public static function generate(mixed $expected, mixed $actual): self
    {
        return self::fromLines(
            explode("\n", ValueExporter::export($expected)),
            explode("\n", ValueExporter::export($actual)),
        );
    }

    /**
     * @param list<string> $expected
     * @param list<string> $actual
     */
    public static function fromLines(array $expected, array $actual): self
    {
        $prefixLength = self::commonPrefixLength($expected, $actual);
        $suffixLength = self::commonSuffixLength($expected, $actual, $prefixLength);

        $expectedMiddle = array_slice($expected, $prefixLength, count($expected) - $prefixLength - $suffixLength);
        $actualMiddle = array_slice($actual, $prefixLength, count($actual) - $prefixLength - $suffixLength);

        $middle = count($expectedMiddle) * count($actualMiddle) > self::MAX_LCS_CELLS
            ? self::positionalDiff($expectedMiddle, $actualMiddle)
            : self::lcsDiff($expectedMiddle, $actualMiddle);

        return new self([
            ...self::sameLines(array_slice($expected, 0, $prefixLength)),
            ...$middle,
            ...self::sameLines(array_slice($expected, count($expected) - $suffixLength)),
        ]);
    }

    public function hasChanges(): bool
    {
        return array_any($this->lines, static fn(DiffLine $line): bool => $line->type !== DiffLineType::Same);
    }

    /**
     * The changed lines with up to $context unchanged lines around each change, in order. A null
     * entry stands for unchanged lines that were left out. Linear in the number of lines.
     *
     * @return list<DiffLine|null>
     */
    public function changesWithContext(int $context): array
    {
        $isVisible = [];
        foreach ($this->lines as $index => $line) {
            if ($line->type !== DiffLineType::Same) {
                for ($near = max(0, $index - $context); $near <= $index + $context; $near++) {
                    $isVisible[$near] = true;
                }
            }
        }

        $visible = [];
        $isFolding = false;
        foreach ($this->lines as $index => $line) {
            if (!isset($isVisible[$index])) {
                $isFolding = true;
                continue;
            }
            if ($isFolding) {
                $visible[] = null;
                $isFolding = false;
            }
            $visible[] = $line;
        }

        return $visible;
    }

    /**
     * @param list<string> $expected
     * @param list<string> $actual
     * @return list<DiffLine>
     */
    private static function lcsDiff(array $expected, array $actual): array
    {
        $expectedCount = count($expected);
        $actualCount = count($actual);

        // $lengths[$i][$j] = LCS length of $expected[$i..] and $actual[$j..]
        $lengths = array_fill(0, $expectedCount + 1, array_fill(0, $actualCount + 1, 0));
        for ($i = $expectedCount - 1; $i >= 0; $i--) {
            for ($j = $actualCount - 1; $j >= 0; $j--) {
                $lengths[$i][$j] = $expected[$i] === $actual[$j]
                    ? $lengths[$i + 1][$j + 1] + 1
                    : max($lengths[$i + 1][$j], $lengths[$i][$j + 1]);
            }
        }

        $lines = [];
        $i = $j = 0;
        while ($i < $expectedCount && $j < $actualCount) {
            if ($expected[$i] === $actual[$j]) {
                $lines[] = new DiffLine(DiffLineType::Same, $expected[$i++]);
                $j++;
            } elseif ($lengths[$i + 1][$j] >= $lengths[$i][$j + 1]) {
                $lines[] = new DiffLine(DiffLineType::ExpectedOnly, $expected[$i++]);
            } else {
                $lines[] = new DiffLine(DiffLineType::ActualOnly, $actual[$j++]);
            }
        }
        while ($i < $expectedCount) {
            $lines[] = new DiffLine(DiffLineType::ExpectedOnly, $expected[$i++]);
        }
        while ($j < $actualCount) {
            $lines[] = new DiffLine(DiffLineType::ActualOnly, $actual[$j++]);
        }

        return $lines;
    }

    /**
     * @param list<string> $expected
     * @param list<string> $actual
     * @return list<DiffLine>
     */
    private static function positionalDiff(array $expected, array $actual): array
    {
        $lines = [];
        for ($i = 0, $max = max(count($expected), count($actual)); $i < $max; $i++) {
            $expectedLine = $expected[$i] ?? null;
            $actualLine = $actual[$i] ?? null;
            if ($expectedLine === $actualLine) {
                $lines[] = new DiffLine(DiffLineType::Same, $expectedLine);
                continue;
            }
            if ($expectedLine !== null) {
                $lines[] = new DiffLine(DiffLineType::ExpectedOnly, $expectedLine);
            }
            if ($actualLine !== null) {
                $lines[] = new DiffLine(DiffLineType::ActualOnly, $actualLine);
            }
        }

        return $lines;
    }

    /**
     * @param list<string> $lines
     * @return list<DiffLine>
     */
    private static function sameLines(array $lines): array
    {
        return array_map(static fn(string $line): DiffLine => new DiffLine(DiffLineType::Same, $line), $lines);
    }

    /**
     * @param list<string> $expected
     * @param list<string> $actual
     */
    private static function commonPrefixLength(array $expected, array $actual): int
    {
        $max = min(count($expected), count($actual));
        $length = 0;
        while ($length < $max && $expected[$length] === $actual[$length]) {
            $length++;
        }

        return $length;
    }

    /**
     * @param list<string> $expected
     * @param list<string> $actual
     */
    private static function commonSuffixLength(array $expected, array $actual, int $prefixLength): int
    {
        $expectedCount = count($expected);
        $actualCount = count($actual);
        $max = min($expectedCount, $actualCount) - $prefixLength;
        $length = 0;
        while ($length < $max && $expected[$expectedCount - 1 - $length] === $actual[$actualCount - 1 - $length]) {
            $length++;
        }

        return $length;
    }
}
