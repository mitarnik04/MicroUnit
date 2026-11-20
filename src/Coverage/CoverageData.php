<?php

namespace MicroUnit\Coverage;

/**
 * Coverage of a run: per file, the hits of every executable line and the functions it contains.
 * Not readonly only to remember the per-file figures, which every report asks for again.
 */
final class CoverageData
{
    /** @var array<string, array{totals: array{executableLines: int, coveredLines: int, functions: int, coveredFunctions: int}, functions: list<array{name: string, startLine: int, endLine: int, isCovered: bool, hits: int}>}> */
    private array $summaries = [];

    /** @param array<string, array{lines: array<int, int>, functions: list<array{name: string, startLine: int, endLine: int}>}> $files */
    public function __construct(
        public readonly array $files = [],
    ) {}

    /** Adds up two runs (e.g. parallel workers): hits per line are summed. */
    public function merge(self $other): self
    {
        $files = $this->files;
        foreach ($other->files as $file => $coverage) {
            if (!isset($files[$file])) {
                $files[$file] = $coverage;
                continue;
            }
            foreach ($coverage['lines'] as $line => $hits) {
                $files[$file]['lines'][$line] = ($files[$file]['lines'][$line] ?? 0) + $hits;
            }
        }
        ksort($files);

        return new self($files);
    }

    /** @return array{executableLines: int, coveredLines: int, functions: int, coveredFunctions: int} */
    public function totals(): array
    {
        $totals = ['executableLines' => 0, 'coveredLines' => 0, 'functions' => 0, 'coveredFunctions' => 0];
        foreach (array_keys($this->files) as $file) {
            foreach ($this->fileTotals($file) as $key => $value) {
                $totals[$key] += $value;
            }
        }

        return $totals;
    }

    /** @return array{executableLines: int, coveredLines: int, functions: int, coveredFunctions: int} */
    public function fileTotals(string $file): array
    {
        return $this->summary($file)['totals'];
    }

    /**
     * Functions that contain executable lines. A function counts as covered when all of its
     * executable lines ran (the same definition PHPUnit uses).
     *
     * @return list<array{name: string, startLine: int, endLine: int, isCovered: bool, hits: int}>
     */
    public function functionsOf(string $file): array
    {
        return $this->summary($file)['functions'];
    }

    public static function percentage(int $covered, int $total): float
    {
        return $total === 0 ? 100.0 : round($covered / $total * 100, 2);
    }

    /** @return array{totals: array{executableLines: int, coveredLines: int, functions: int, coveredFunctions: int}, functions: list<array{name: string, startLine: int, endLine: int, isCovered: bool, hits: int}>} */
    private function summary(string $file): array
    {
        if (isset($this->summaries[$file])) {
            return $this->summaries[$file];
        }

        $lines = $this->files[$file]['lines'];
        $functions = [];
        foreach ($this->files[$file]['functions'] as $function) {
            $ownLines = array_filter(
                $lines,
                static fn(int $line): bool => $line >= $function['startLine'] && $line <= $function['endLine'],
                ARRAY_FILTER_USE_KEY,
            );
            if ($ownLines === []) {
                continue;
            }
            $functions[] = [
                ...$function,
                'isCovered' => !in_array(0, $ownLines, true),
                'hits' => (int) min($ownLines),
            ];
        }

        return $this->summaries[$file] = [
            'totals' => [
                'executableLines' => count($lines),
                'coveredLines' => count(array_filter($lines, static fn(int $hits): bool => $hits > 0)),
                'functions' => count($functions),
                'coveredFunctions' => count(array_filter($functions, static fn(array $function): bool => $function['isCovered'])),
            ],
            'functions' => $functions,
        ];
    }
}
