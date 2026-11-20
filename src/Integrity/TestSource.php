<?php

namespace MicroUnit\Integrity;

use MicroUnit\Migrate\Code;

/**
 * What a MicroUnit test file says about each of its tests, read from source: how many assertions
 * and skips a test contains, its expected values, its data sets and trivially true assertions.
 */
final readonly class TestSource
{
    /** Assert methods that do not assert anything. */
    private const array NOT_ASSERTIONS = ['skip', 'skipif'];

    /** Assert methods whose first argument is the expected value. */
    private const array EXPECTING = ['exact', 'equals', 'equalswithdelta', 'equalswithinpercent', 'jsonequals', 'equivalent', 'countequals', 'equalsignoringcase', 'equalsignoringwhitespace'];

    /**
     * @param array<string, array{line: int, assertions: int, skips: int, expected: list<string>, trivial: list<string>, dataSets: ?int}> $tests by test id ("Suite::name")
     */
    private function __construct(
        public array $tests,
    ) {}

    public static function parse(string $code): self
    {
        try {
            $tokens = Code::tokenize($code);
        } catch (\Throwable) {
            return new self([]);
        }
        $tests = [];
        // Ids like the runner's: the suite of TestSetup::getTester('Suite'), then the test name.
        $suite = preg_match('/getTester\(\s*([\'"])(.+?)\1\s*\)/', $code, $found) ? $found[2] . '::' : '';
        foreach ($tokens as $i => $token) {
            if ($token->id !== T_STRING || !in_array($token->text, ['define', 'defineGroup'], true)) {
                continue;
            }
            $previous = Code::previous($tokens, $i);
            $open = Code::next($tokens, $i);
            if ($previous === null || $tokens[$previous]->id !== T_OBJECT_OPERATOR || $open === null || Code::char($tokens[$open]) !== '(') {
                continue;
            }
            $arguments = Code::arguments($tokens, $open, Code::closing($tokens, $open));
            if (count($arguments) < 2) {
                continue;
            }
            $name = trim(Code::text($tokens, $arguments[0][0], $arguments[0][1]));
            if (!preg_match('/^([\'"])(.*)\1$/s', $name, $match)) {
                continue;
            }
            $tests[$suite . $match[2]] = [
                'line' => $token->line,
                ...self::analyze($tokens, $arguments[1][0], $arguments[1][1]),
                'dataSets' => $token->text === 'defineGroup' && isset($arguments[2]) ? self::dataSetCount($tokens, $arguments[2][0], $arguments[2][1]) : null,
            ];
        }

        return new self($tests);
    }

    /**
     * How the tests of $before were weakened in this version of the file.
     *
     * @return array<string, list<string>> test id => findings, for the tests with findings
     */
    public function weakenedSince(self $before): array
    {
        $findings = [];
        foreach ($before->tests as $name => $old) {
            $testFindings = self::compare($old, $this->tests[$name] ?? null);
            if ($testFindings !== []) {
                $findings[$name] = $testFindings;
            }
        }

        return $findings;
    }

    /**
     * @param array{line: int, assertions: int, skips: int, expected: list<string>, trivial: list<string>, dataSets: ?int} $old
     * @param array{line: int, assertions: int, skips: int, expected: list<string>, trivial: list<string>, dataSets: ?int}|null $new
     * @return list<string>
     */
    private static function compare(array $old, ?array $new): array
    {
        if ($new === null) {
            return ['test removed'];
        }
        $findings = [];
        if ($new['assertions'] < $old['assertions']) {
            $findings[] = sprintf('assertions removed (%d before, %d now)', $old['assertions'], $new['assertions']);
        }
        if ($new['skips'] > $old['skips']) {
            $findings[] = 'now skipped';
        }
        if ($old['dataSets'] !== null && $new['dataSets'] !== null && $new['dataSets'] < $old['dataSets']) {
            $findings[] = sprintf('data sets removed (%d before, %d now)', $old['dataSets'], $new['dataSets']);
        }
        foreach (array_diff($new['trivial'], $old['trivial']) as $trivial) {
            $findings[] = "assertion that cannot fail: $trivial";
        }
        // Compared as multisets: an assertion added in front must not make every later one look changed.
        $oldLiterals = array_values(array_filter($old['expected'], self::isLiteral(...)));
        $newLiterals = array_values(array_filter($new['expected'], self::isLiteral(...)));
        $added = self::without($newLiterals, $oldLiterals);
        foreach (self::without($oldLiterals, $newLiterals) as $index => $expected) {
            if (isset($added[$index])) {
                $findings[] = "expected value changed: $expected → {$added[$index]}";
            }
        }

        return $findings;
    }

    /**
     * The values of $values that $others does not have, counting duplicates.
     *
     * @param list<string> $values
     * @param list<string> $others
     * @return list<string>
     */
    private static function without(array $values, array $others): array
    {
        $remaining = [];
        foreach ($values as $value) {
            $index = array_search($value, $others, true);
            if ($index === false) {
                $remaining[] = $value;
            } else {
                unset($others[$index]);
            }
        }

        return $remaining;
    }

    private static function isLiteral(string $code): bool
    {
        return preg_match('/^(-?[\d.]+|true|false|null|([\'"]).*\2)$/is', $code) === 1;
    }

    /**
     * @param list<\PhpToken> $tokens
     * @return array{assertions: int, skips: int, expected: list<string>, trivial: list<string>}
     */
    private static function analyze(array $tokens, int $from, int $to): array
    {
        $result = ['assertions' => 0, 'skips' => 0, 'expected' => [], 'trivial' => []];
        for ($i = $from; $i <= $to; $i++) {
            $token = $tokens[$i];
            if ($token->id !== T_STRING) {
                continue;
            }
            $next = Code::next($tokens, $i);
            $previous = Code::previous($tokens, $i);
            $isStaticCall = $next !== null && $tokens[$next]->id === T_DOUBLE_COLON;
            if ($isStaticCall && in_array($token->text, ['Assert', 'AssertMock'], true)) {
                $methodIndex = (int) Code::next($tokens, $next);
                $method = strtolower($tokens[$methodIndex]->text);
                if (in_array($method, self::NOT_ASSERTIONS, true)) {
                    $result['skips']++;
                    continue;
                }
                $result['assertions']++;
                $open = Code::next($tokens, $methodIndex);
                if ($open !== null && Code::char($tokens[$open]) === '(') {
                    $arguments = array_map(static fn(array $range): string => trim(Code::text($tokens, $range[0], $range[1])), Code::arguments($tokens, $open, Code::closing($tokens, $open)));
                    if (in_array($method, self::EXPECTING, true) && isset($arguments[0])) {
                        $result['expected'][] = $arguments[0];
                    }
                    $trivial = self::trivial($method, $arguments);
                    if ($trivial !== null) {
                        $result['trivial'][] = "{$token->text}::{$tokens[$methodIndex]->text}($trivial)";
                    }
                }
                continue;
            }
            // Chained mock checks: AssertMock::begin($mock)->isCalledOnce('send')->isCalledWith(...)
            $isChained = $previous !== null && $tokens[$previous]->id === T_OBJECT_OPERATOR && str_starts_with($token->text, 'isCalled');
            // Project assertion helpers: assertValidOrder($order)
            $isHelper = !$isStaticCall && $next !== null && Code::char($tokens[$next]) === '(' && str_starts_with($token->text, 'assert')
                && ($previous === null || !in_array($tokens[$previous]->id, [T_OBJECT_OPERATOR, T_FUNCTION, T_DOUBLE_COLON], true));
            if ($isChained || $isHelper) {
                $result['assertions']++;
            }
        }

        return $result;
    }

    /** The arguments of an assertion that cannot fail, or null. @param list<string> $arguments */
    private static function trivial(string $method, array $arguments): ?string
    {
        $first = strtolower($arguments[0] ?? '');
        $isTrivial = match ($method) {
            'istrue' => $first === 'true',
            'isfalse' => $first === 'false',
            'isnull' => $first === 'null',
            'notnull', 'notempty' => preg_match('/^([\'"]).+\1$|^-?\d/', $arguments[0] ?? '') === 1,
            'exact', 'equals', 'equivalent' => isset($arguments[1]) && preg_replace('/\s+/', '', $arguments[0]) === preg_replace('/\s+/', '', $arguments[1]),
            default => false,
        };

        return $isTrivial ? implode(', ', $arguments) : null;
    }

    /** The number of data sets of an array literal, null when they come from elsewhere. @param list<\PhpToken> $tokens */
    private static function dataSetCount(array $tokens, int $from, int $to): ?int
    {
        $open = $tokens[$from]->isIgnorable() ? Code::next($tokens, $from) : $from;
        if ($open === null || Code::char($tokens[$open]) !== '[' || Code::closing($tokens, $open) !== (Code::previous($tokens, $to + 1) ?? $to)) {
            return null;
        }

        return count(Code::arguments($tokens, $open, Code::closing($tokens, $open)));
    }
}
