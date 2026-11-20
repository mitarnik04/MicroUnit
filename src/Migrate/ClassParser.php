<?php

namespace MicroUnit\Migrate;

/** Reads the classes of a PHP file into ParsedClass models (structure only; bodies stay text). */
final class ClassParser
{
    private const array MODIFIERS = [T_PUBLIC, T_PROTECTED, T_PRIVATE, T_STATIC, T_FINAL, T_ABSTRACT, T_READONLY, T_VAR];

    private function __construct() {}

    /** @return list<ParsedClass> */
    public static function parse(string $code, string $file): array
    {
        return self::classes(Code::tokenize($code), $file);
    }

    /**
     * @param list<\PhpToken> $tokens a whole file
     * @return list<ParsedClass>
     */
    public static function classes(array $tokens, string $file): array
    {
        $namespace = null;
        $uses = [];
        $hasStrictTypes = false;
        $classes = [];
        $pendingAttributes = [];
        $isAbstract = false;

        for ($i = 0, $count = count($tokens); $i < $count; $i++) {
            $token = $tokens[$i];
            switch ($token->id) {
                case T_DECLARE:
                    $close = Code::closing($tokens, (int) Code::next($tokens, $i));
                    $hasStrictTypes = $hasStrictTypes || preg_match('/strict_types\s*=\s*1/', Code::text($tokens, $i, $close)) === 1;
                    $i = $close;
                    break;
                case T_NAMESPACE:
                    [$namespace, $i] = self::namespace($tokens, $i);
                    $uses = [];
                    break;
                case T_USE:
                    $end = self::statementEnd($tokens, $i);
                    $uses[] = trim(Code::text($tokens, $i, $end - 1));
                    $i = $end;
                    break;
                case T_ATTRIBUTE:
                    $close = Code::closing($tokens, $i);
                    $pendingAttributes = [...$pendingAttributes, ...self::attributes($tokens, $i, $close)];
                    $i = $close;
                    break;
                case T_ABSTRACT:
                    $isAbstract = true;
                    break;
                case T_CLASS:
                case T_TRAIT:
                    $previous = Code::previous($tokens, $i);
                    if ($previous !== null && $tokens[$previous]->id === T_NEW) {
                        break; // an anonymous class
                    }
                    [$class, $i] = self::parseClass($tokens, $i, $file, $namespace, $uses, $hasStrictTypes, $isAbstract, $pendingAttributes);
                    $classes[] = $class;
                    $pendingAttributes = [];
                    $isAbstract = false;
                    break;
                case T_FUNCTION:
                case T_INTERFACE:
                case T_ENUM:
                    // Skip other declarations wholesale.
                    for ($j = $i; $j < $count && Code::char($tokens[$j]) !== '{' && Code::char($tokens[$j]) !== ';'; $j++);
                    $i = Code::char($tokens[$j]) === '{' ? Code::closing($tokens, $j) : $j;
                    $pendingAttributes = [];
                    $isAbstract = false;
                    break;
            }
        }

        return $classes;
    }

    /**
     * Every top-level declaration of a file (class, interface, trait, enum, function) with its
     * source text, including the doc comment, attributes and modifiers in front of it.
     *
     * @param list<\PhpToken> $tokens a whole file
     * @return list<array{kind: string, name: string, fullName: string, code: string, namespace: ?string, uses: list<string>}>
     */
    public static function declarations(array $tokens): array
    {
        $namespace = null;
        $uses = [];
        $start = null;
        $declarations = [];
        for ($i = 0, $count = count($tokens); $i < $count; $i++) {
            $token = $tokens[$i];
            if ($token->isIgnorable() && $token->id !== T_DOC_COMMENT) {
                continue;
            }
            switch ($token->id) {
                case T_NAMESPACE:
                    [$namespace, $i] = self::namespace($tokens, $i);
                    [$uses, $start] = [[], null];
                    break;
                case T_USE:
                    $end = self::statementEnd($tokens, $i);
                    $uses[] = trim(Code::text($tokens, $i, $end - 1));
                    [$i, $start] = [$end, null];
                    break;
                case T_DECLARE:
                case T_CONST:
                    [$i, $start] = [self::statementEnd($tokens, $i), null];
                    break;
                case T_DOC_COMMENT:
                case T_ABSTRACT:
                case T_FINAL:
                case T_READONLY:
                    $start ??= $i;
                    break;
                case T_ATTRIBUTE:
                    $start ??= $i;
                    $i = Code::closing($tokens, $i);
                    break;
                case T_CLASS:
                case T_INTERFACE:
                case T_TRAIT:
                case T_ENUM:
                case T_FUNCTION:
                    $nameIndex = Code::next($tokens, $i);
                    if ($nameIndex === null || $tokens[$nameIndex]->id !== T_STRING) {
                        $start = null;
                        break;
                    }
                    $open = $nameIndex;
                    while (Code::char($tokens[$open]) !== '{') {
                        $open++;
                    }
                    $close = Code::closing($tokens, $open);
                    $name = $tokens[$nameIndex]->text;
                    $declarations[] = [
                        'kind' => strtolower($token->text),
                        'name' => $name,
                        'fullName' => ($namespace === null ? '' : $namespace . '\\') . $name,
                        'code' => Code::text($tokens, $start ?? $i, $close),
                        'namespace' => $namespace,
                        'uses' => $uses,
                    ];
                    [$i, $start] = [$close, null];
                    break;
                default:
                    $start = null;
                    if (Code::char($token) === '{') {
                        $i = Code::closing($tokens, $i);
                    }
            }
        }

        return $declarations;
    }

    /**
     * `namespace Name;` or `namespace Name {` (the block's contents are read like the rest of the
     * file): the name (null for the global namespace) and the index of the ";" or "{".
     *
     * @param list<\PhpToken> $tokens
     * @return array{?string, int}
     */
    private static function namespace(array $tokens, int $index): array
    {
        $end = $index;
        while (Code::char($tokens[$end]) !== ';' && Code::char($tokens[$end]) !== '{') {
            $end++;
        }
        $name = trim(Code::text($tokens, $index + 1, $end - 1));

        return [$name === '' ? null : $name, $end];
    }

    /**
     * @param list<\PhpToken> $tokens
     * @param list<string> $uses
     * @param list<array{name: string, arguments: ?string}> $attributes
     * @return array{ParsedClass, int}
     */
    private static function parseClass(array $tokens, int $classIndex, string $file, ?string $namespace, array $uses, bool $hasStrictTypes, bool $isAbstract, array $attributes): array
    {
        $nameIndex = (int) Code::next($tokens, $classIndex);
        $name = $tokens[$nameIndex]->text;
        $parent = null;
        $open = $nameIndex;
        while (Code::char($tokens[$open]) !== '{') {
            if ($tokens[$open]->id === T_EXTENDS) {
                $parent = $tokens[(int) Code::next($tokens, $open)]->text;
            }
            $open++;
        }
        $close = Code::closing($tokens, $open);

        $traits = [];
        $constants = [];
        $properties = [];
        $methods = [];
        $memberAttributes = [];
        $docComment = null;
        $modifiers = [];

        for ($i = $open + 1; $i < $close; $i++) {
            $token = $tokens[$i];
            if ($token->id === T_DOC_COMMENT) {
                $docComment = $token->text;
            } elseif ($token->id === T_ATTRIBUTE) {
                $end = Code::closing($tokens, $i);
                $memberAttributes = [...$memberAttributes, ...self::attributes($tokens, $i, $end)];
                $i = $end;
            } elseif (in_array($token->id, self::MODIFIERS, true)) {
                $modifiers[] = $token->id;
            } elseif ($token->id === T_USE) {
                $end = $i;
                while (Code::char($tokens[$end]) !== ';' && Code::char($tokens[$end]) !== '{') {
                    $end++;
                }
                foreach (explode(',', Code::text($tokens, $i + 1, $end - 1)) as $trait) {
                    $traits[] = trim($trait);
                }
                $i = Code::char($tokens[$end]) === '{' ? Code::closing($tokens, $end) : $end;
                [$memberAttributes, $docComment, $modifiers] = [[], null, []];
            } elseif ($token->id === T_CONST) {
                $end = self::statementEnd($tokens, $i);
                $constants = [...$constants, ...self::constants($tokens, $i + 1, $end - 1)];
                $i = $end;
                [$memberAttributes, $docComment, $modifiers] = [[], null, []];
            } elseif ($token->id === T_FUNCTION) {
                [$method, $i] = self::parseMethod($tokens, $i, $modifiers, $memberAttributes, $docComment);
                $methods[] = $method;
                [$memberAttributes, $docComment, $modifiers] = [[], null, []];
            } elseif ($token->id === T_VARIABLE || ($modifiers !== [] && !$token->isIgnorable())) {
                // A property declaration: [modifiers] [type] $name [= default] [, $other ...];
                $end = self::statementEnd($tokens, $i);
                $properties = [...$properties, ...self::properties($tokens, $i, $end - 1, in_array(T_STATIC, $modifiers, true))];
                $i = $end;
                [$memberAttributes, $docComment, $modifiers] = [[], null, []];
            }
        }

        return [new ParsedClass($file, $namespace, $uses, $hasStrictTypes, $name, $parent, $isAbstract, $traits, $constants, $properties, $methods, $attributes, $tokens[$classIndex]->line), $close];
    }

    /**
     * @param list<\PhpToken> $tokens
     * @param list<int> $modifiers
     * @param list<array{name: string, arguments: ?string}> $attributes
     * @return array{ParsedMethod, int}
     */
    private static function parseMethod(array $tokens, int $functionIndex, array $modifiers, array $attributes, ?string $docComment): array
    {
        $nameIndex = (int) Code::next($tokens, $functionIndex);
        if (Code::char($tokens[$nameIndex]) === '&') {
            $nameIndex = (int) Code::next($tokens, $nameIndex);
        }
        $open = (int) Code::next($tokens, $nameIndex);
        $close = Code::closing($tokens, $open);

        $returnType = null;
        $bodyStart = (int) Code::next($tokens, $close);
        if (Code::char($tokens[$bodyStart]) === ':') {
            $typeEnd = $bodyStart + 1;
            while (Code::char($tokens[$typeEnd]) !== '{' && Code::char($tokens[$typeEnd]) !== ';') {
                $typeEnd++;
            }
            $returnType = trim(Code::text($tokens, $bodyStart + 1, $typeEnd - 1));
            $bodyStart = $typeEnd;
        }
        $body = null;
        $end = $bodyStart;
        if (Code::char($tokens[$bodyStart]) === '{') {
            $end = Code::closing($tokens, $bodyStart);
            $body = Code::text($tokens, $bodyStart, $end);
        }

        return [new ParsedMethod(
            $tokens[$nameIndex]->text,
            in_array(T_STATIC, $modifiers, true),
            $attributes,
            $docComment,
            Code::text($tokens, $open + 1, $close - 1),
            $returnType,
            $body,
            $tokens[$bodyStart]->line,
        ), $end];
    }

    /**
     * @param list<\PhpToken> $tokens
     * @return list<array{name: string, arguments: ?string}>
     */
    private static function attributes(array $tokens, int $open, int $close): array
    {
        $attributes = [];
        foreach (Code::arguments($tokens, $open, $close) as [$from, $to]) {
            $nameIndex = $tokens[$from]->isIgnorable() ? (int) Code::next($tokens, $from) : $from;
            $name = ltrim($tokens[$nameIndex]->text, '\\');
            $short = str_contains($name, '\\') ? substr($name, (int) strrpos($name, '\\') + 1) : $name;
            $argumentsOpen = Code::next($tokens, $nameIndex);
            $arguments = null;
            if ($argumentsOpen !== null && $argumentsOpen <= $to && Code::char($tokens[$argumentsOpen]) === '(') {
                $arguments = Code::text($tokens, $argumentsOpen + 1, Code::closing($tokens, $argumentsOpen) - 1);
            }
            $attributes[] = ['name' => $short, 'arguments' => $arguments];
        }

        return $attributes;
    }

    /**
     * `[type] NAME = value, OTHER = value` of a class constant declaration.
     *
     * @param list<\PhpToken> $tokens
     * @return list<array{name: string, value: string}>
     */
    private static function constants(array $tokens, int $from, int $to): array
    {
        $constants = [];
        foreach (Code::ranges($tokens, $from, $to) as [$start, $end]) {
            [$left, $value] = explode('=', Code::text($tokens, $start, $end), 2) + [1 => ''];
            $words = preg_split('/\s+/', trim($left)) ?: [];
            $constants[] = ['name' => (string) end($words), 'value' => trim($value)];
        }

        return $constants;
    }

    /**
     * `[type] $name [= default] [, $other [= default] ...]` of a property declaration.
     *
     * @param list<\PhpToken> $tokens
     * @return list<ParsedProperty>
     */
    private static function properties(array $tokens, int $from, int $to, bool $isStatic): array
    {
        $first = $from;
        while ($first <= $to && $tokens[$first]->id !== T_VARIABLE) {
            $first++;
        }
        $type = trim(Code::text($tokens, $from, $first - 1)) ?: null;
        $properties = [];
        foreach (Code::ranges($tokens, $first, $to) as [$start, $end]) {
            $variable = $tokens[$start]->id === T_VARIABLE ? $start : (int) Code::next($tokens, $start);
            $equals = Code::next($tokens, $variable);
            $hasDefault = $equals !== null && $equals <= $end && Code::char($tokens[$equals]) === '=';
            $properties[] = new ParsedProperty(substr($tokens[$variable]->text, 1), $type, $hasDefault ? trim(Code::text($tokens, $equals + 1, $end)) : null, $isStatic);
        }

        return $properties;
    }

    /** Index of the ";" ending the statement that starts at $from. @param list<\PhpToken> $tokens */
    private static function statementEnd(array $tokens, int $from): int
    {
        for ($i = $from, $count = count($tokens); $i < $count; $i++) {
            $text = Code::char($tokens[$i]);
            if ($text === ';') {
                return $i;
            }
            if ($text === '(' || $text === '[' || $text === '{') {
                $i = Code::closing($tokens, $i);
            }
        }

        return $count - 1;
    }
}
