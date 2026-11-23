<?php

namespace MicroUnit\Migrate;

/**
 * Token helpers for reading and rewriting PHP source: bracket matching, argument splitting and
 * indentation. Works on PhpToken lists; positions are list indexes.
 */
final class Code
{
    private const array OPENING = ['(' => ')', '[' => ']', '{' => '}'];

    private function __construct() {}

    /** @return list<\PhpToken> */
    public static function tokenize(string $code): array
    {
        return array_values(\PhpToken::tokenize($code, TOKEN_PARSE));
    }

    /**
     * Tokens of a function body ("{ ... }") or other fragment, without the wrapper needed to
     * tokenize it correctly (`yield`, `return` and keywords used as names).
     *
     * @return list<\PhpToken>
     */
    public static function tokenizeBody(string $body): array
    {
        $tokens = self::tokenize("<?php function __migrate()\n$body");

        // Drop "<?php", "function", " ", "__migrate", "(", ")" and the newline.
        return array_slice($tokens, 7);
    }

    /**
     * Tokens of a piece of code that is not a statement on its own: a parameter list, a type, a
     * default value, attribute arguments.
     *
     * @return list<\PhpToken>
     */
    public static function tokenizeFragment(string $fragment): array
    {
        return array_values(array_slice(\PhpToken::tokenize('<?php ' . $fragment), 1));
    }

    /**
     * Splits a token list at its top-level commas into [from, to] ranges.
     *
     * @param list<\PhpToken> $tokens
     * @return list<array{int, int}>
     */
    public static function split(array $tokens): array
    {
        return self::ranges($tokens, 0, count($tokens) - 1);
    }

    /**
     * Splits tokens $from..$to at their top-level commas into [from, to] ranges; an empty last
     * range (trailing comma) is left out.
     *
     * @param list<\PhpToken> $tokens
     * @return list<array{int, int}>
     */
    public static function ranges(array $tokens, int $from, int $to): array
    {
        $ranges = [];
        $start = $from;
        for ($i = $from; $i <= $to; $i++) {
            if (self::isOpening($tokens[$i])) {
                $i = self::closing($tokens, $i);
            } elseif (self::char($tokens[$i]) === ',') {
                $ranges[] = [$start, $i - 1];
                $start = $i + 1;
            }
        }
        if (trim(self::text($tokens, $start, $to)) !== '') {
            $ranges[] = [$start, $to];
        }

        return $ranges;
    }

    /** An opening bracket: "(", "[", "{", "#[" or a string-interpolation brace. */
    public static function isOpening(\PhpToken $token): bool
    {
        return isset(self::OPENING[self::char($token)]) || $token->id === T_CURLY_OPEN || $token->id === T_DOLLAR_OPEN_CURLY_BRACES || $token->id === T_ATTRIBUTE;
    }

    /**
     * The character of a single-character token (punctuation), '' for every other token. Compare
     * with this, not with ->text: a string fragment like the ")" in "($x)" has that text too.
     */
    public static function char(\PhpToken $token): string
    {
        return $token->id < 256 ? $token->text : '';
    }

    /** @param list<\PhpToken> $tokens */
    public static function text(array $tokens, int $from = 0, ?int $to = null): string
    {
        $text = '';
        $to ??= count($tokens) - 1;
        for ($i = $from; $i <= $to; $i++) {
            $text .= $tokens[$i]->text;
        }

        return $text;
    }

    /**
     * Index of the bracket closing the one at $open ("(", "[", "{"; string-interpolation braces count).
     *
     * @param list<\PhpToken> $tokens
     */
    public static function closing(array $tokens, int $open): int
    {
        $depth = 0;
        for ($i = $open, $count = count($tokens); $i < $count; $i++) {
            $text = self::char($tokens[$i]);
            if (self::isOpening($tokens[$i])) {
                $depth++;
            } elseif ($text === ')' || $text === ']' || $text === '}') {
                if (--$depth === 0) {
                    return $i;
                }
            }
        }

        throw new \UnexpectedValueException('Unbalanced brackets.');
    }

    /**
     * Next index that is not whitespace or a comment, or null.
     *
     * @param list<\PhpToken> $tokens
     */
    public static function next(array $tokens, int $index): ?int
    {
        for ($i = $index + 1, $count = count($tokens); $i < $count; $i++) {
            if (!$tokens[$i]->isIgnorable()) {
                return $i;
            }
        }

        return null;
    }

    /**
     * Previous index that is not whitespace or a comment, or null.
     *
     * @param list<\PhpToken> $tokens
     */
    public static function previous(array $tokens, int $index): ?int
    {
        for ($i = $index - 1; $i >= 0; $i--) {
            if (!$tokens[$i]->isIgnorable()) {
                return $i;
            }
        }

        return null;
    }

    /**
     * The arguments between the parentheses at $open and $close, split at top-level commas,
     * as [from, to] index pairs (trailing comma tolerated).
     *
     * @param list<\PhpToken> $tokens
     * @return list<array{int, int}>
     */
    public static function arguments(array $tokens, int $open, int $close): array
    {
        return self::ranges($tokens, $open + 1, $close - 1);
    }

    /**
     * Top-level statements inside "{ ... }" tokens as [from, to] ranges; comments before a
     * statement belong to it.
     *
     * @param list<\PhpToken> $tokens
     * @return list<array{int, int}>
     */
    public static function statements(array $tokens): array
    {
        $statements = [];
        $last = count($tokens) - 2;
        $i = 1;
        while ($i <= $last) {
            while ($i <= $last && $tokens[$i]->id === T_WHITESPACE) {
                $i++;
            }
            if ($i > $last) {
                break;
            }
            $start = $i;
            $first = $tokens[$i]->isIgnorable() ? self::next($tokens, $i) : $i;
            $isBlock = $first !== null && in_array($tokens[$first]->id, [T_IF, T_FOREACH, T_FOR, T_WHILE, T_SWITCH, T_TRY], true);
            for (; $i <= $last; $i++) {
                if (self::isOpening($tokens[$i])) {
                    $isBrace = self::char($tokens[$i]) === '{';
                    $i = self::closing($tokens, $i);
                    if ($isBrace && $isBlock) {
                        $next = self::next($tokens, $i);
                        if ($next === null || !in_array($tokens[$next]->id, [T_ELSE, T_ELSEIF, T_CATCH, T_FINALLY], true)) {
                            break;
                        }
                    }
                    continue;
                }
                if (self::char($tokens[$i]) === ';') {
                    break;
                }
            }
            $statements[] = [$start, min($i, $last)];
            $i++;
        }

        return $statements;
    }

    /**
     * Shifts every line of $code left by $columns (or right when negative). Only indentation that is
     * code or comment changes; string and heredoc contents are left alone.
     */
    public static function reindent(string $code, int $columns): string
    {
        if ($columns === 0) {
            return $code;
        }
        $tokens = self::tokenizeFragment($code);
        $shift = static fn(string $text): string => (string) preg_replace_callback(
            '/\n([ \t]*)/',
            static fn(array $match): string => "\n" . ($columns > 0
                ? substr($match[1], min($columns, strspn($match[1], ' ')))
                : str_repeat(' ', -$columns) . $match[1]),
            $text,
        );

        $result = '';
        foreach ($tokens as $token) {
            $isCodeWhitespace = $token->id === T_WHITESPACE || $token->id === T_COMMENT || $token->id === T_DOC_COMMENT;
            $result .= $isCodeWhitespace ? $shift($token->text) : $token->text;
        }

        return $result;
    }

    /** Smallest indentation of the non-empty lines after the first. */
    public static function indentation(string $code): int
    {
        $smallest = PHP_INT_MAX;
        foreach (array_slice(explode("\n", $code), 1) as $line) {
            if (trim($line) !== '') {
                $smallest = min($smallest, strspn($line, ' '));
            }
        }

        return $smallest === PHP_INT_MAX ? 0 : $smallest;
    }

    /** "testAddsTwoNumbers" or "test_adds_two_numbers" => "adds_two_numbers". */
    public static function testName(string $methodName): string
    {
        $name = preg_replace('/^test_?/i', '', $methodName) ?: $methodName;
        $name = (string) preg_replace('/(?<=[a-z0-9])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', '_', $name);

        return strtolower(trim($name, '_')) ?: strtolower($methodName);
    }

    /** The function a parent's version of an overridden helper becomes: wrap => parentWrap (level 1). */
    public static function parentFunctionName(string $method, int $level): string
    {
        return 'parent' . str_repeat('Parent', $level - 1) . ucfirst($method);
    }

    /** "CalculatorTest" => "Calculator". */
    public static function suiteName(string $className): string
    {
        return preg_replace('/Test(Case)?$/', '', $className) ?: $className;
    }
}
