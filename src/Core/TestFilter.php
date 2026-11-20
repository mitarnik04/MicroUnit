<?php

namespace MicroUnit\Core;

/**
 * Selects tests by id ("Suite::test_name"). The pattern is a case-insensitive substring, or a
 * regular expression when written with slashes: `/^Mailer::sends_/`.
 */
final readonly class TestFilter
{
    /** @param array<string, true>|null $ids */
    private function __construct(
        private ?string $regex,
        private ?array $ids = null,
    ) {}

    public static function all(): self
    {
        return new self(null);
    }

    public static function fromPattern(?string $pattern): self
    {
        if ($pattern === null || $pattern === '') {
            return self::all();
        }

        $isRegex = strlen($pattern) > 2 && $pattern[0] === '/' && strrpos($pattern, '/') > 0;
        $regex = $isRegex ? $pattern : '/' . preg_quote($pattern, '/') . '/i';
        if (@preg_match($regex, '') === false) {
            throw new \InvalidArgumentException("Invalid --filter regular expression: $pattern");
        }

        return new self($regex);
    }

    /** @param list<string> $testIds */
    public static function exactIds(array $testIds): self
    {
        return new self(null, array_fill_keys($testIds, true));
    }

    public function accepts(Test $test): bool
    {
        if ($this->ids !== null) {
            return isset($this->ids[$test->id]);
        }

        return $this->regex === null || preg_match($this->regex, $test->id) === 1;
    }
}
