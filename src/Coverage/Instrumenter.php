<?php

namespace MicroUnit\Coverage;

/**
 * Inserts a hit counter in front of every executable statement of a PHP file.
 *
 * It is a small recursive-descent parser on top of PHP's own tokenizer: statements are parsed
 * properly (control structures, functions, classes, closures, arrow functions, property hooks),
 * expressions are scanned as balanced token runs. Probes go on the statement's own line, so line
 * numbers never change. Bodies without braces are wrapped in braces (a no-op in PHP), and code that
 * runs separately from its statement (`elseif` conditions, the `while` of `do…while`, arrow function
 * and short property hook bodies) is probed inside the expression.
 *
 * A line is executable when an executable statement starts on it and covered when that statement
 * started running.
 */
final class Instrumenter
{
    private const array IGNORABLE = [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT];

    private const array CLASS_MODIFIERS = [T_ABSTRACT, T_FINAL, T_READONLY];

    private const array CLASS_KEYWORDS = [T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM];

    private const array MEMBER_MODIFIERS = [
        T_PUBLIC, T_PROTECTED, T_PRIVATE, T_STATIC, T_ABSTRACT, T_FINAL, T_READONLY, T_VAR,
        T_PUBLIC_SET, T_PROTECTED_SET, T_PRIVATE_SET,
    ];

    private const array OPENING_BRACKETS = ['(', '[', '{', T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES];

    private const array CLOSING_BRACKETS = [')', ']', '}'];

    /** Where an arrow function's body ends (at bracket depth 0). */
    private const array ARROW_BODY_TERMINATORS = [',', ')', ']', '}', ';', ':'];

    /** @var list<\PhpToken> */
    private array $tokens;

    /** @var list<int> indexes of the non-whitespace, non-comment tokens */
    private array $significant = [];

    private int $cursor = 0;

    private int $lastConsumed = -1;

    /** @var array<int, string> code inserted before a token */
    private array $before = [];

    /** @var array<int, string> code inserted after a token */
    private array $after = [];

    /** @var array<int, string> replacement text for a token */
    private array $replace = [];

    /** @var array<int, true> */
    private array $executableLines = [];

    /** @var list<array{name: string, startLine: int, endLine: int}> */
    private array $functions = [];

    private string $namespace = '';

    private readonly string $probe;

    private function __construct(string $code, string $file)
    {
        try {
            $this->tokens = array_values(\PhpToken::tokenize($code, TOKEN_PARSE));
        } catch (\ParseError $error) {
            throw new InstrumentationException("Syntax error on line {$error->getLine()}: {$error->getMessage()}", 0, $error);
        }

        foreach ($this->tokens as $index => $token) {
            if (!$token->is(self::IGNORABLE)) {
                $this->significant[] = $index;
            }
        }
        $this->probe = '++\\' . Probe::class . '::$hits[' . var_export($file, true) . ']';
    }

    /** @throws InstrumentationException */
    public static function instrument(string $code, string $file): InstrumentedSource
    {
        $instrumenter = new self($code, $file);
        $instrumenter->parseStatements([]);

        $lines = array_keys($instrumenter->executableLines);
        sort($lines);

        return new InstrumentedSource($instrumenter->render(), $lines, $instrumenter->functions);
    }

    // ---- Statements --------------------------------------------------------------------------

    /** @param list<int|string> $endTokens */
    private function parseStatements(array $endTokens): void
    {
        while ($this->current() !== null && ($endTokens === [] || !$this->isAt($endTokens))) {
            $this->parseStatement();
        }
    }

    /** Parses one statement; a statement that consumes nothing means the parser is out of sync. */
    private function parseStatement(): void
    {
        $cursorBefore = $this->cursor;
        $this->parseStatementAtCursor();
        if ($this->cursor === $cursorBefore) {
            $token = $this->requireCurrent();
            throw new InstrumentationException("Unexpected '$token->text' on line $token->line.");
        }
    }

    private function parseStatementAtCursor(): void
    {
        $token = $this->requireCurrent();
        $index = $this->currentIndex();

        switch (true) {
            case self::matches($token, [T_OPEN_TAG, T_CLOSE_TAG, T_INLINE_HTML, ';']):
                $this->advance();
                return;
            case self::matches($token, T_OPEN_TAG_WITH_ECHO):
                $this->executableLines[$token->line] = true;
                $this->replace[$index] = '<?php ' . $this->probeStatement($token->line) . ' echo ';
                $this->advance();
                $this->scanExpression([';']);
                $this->consumeTerminator();
                return;
            case self::matches($token, '{'):
                $this->parseBlock();
                return;
            case self::matches($token, T_NAMESPACE):
                $this->parseNamespace();
                return;
            case self::matches($token, T_USE):
                $this->scanExpression([';']);
                $this->consumeTerminator();
                return;
            case self::matches($token, T_DECLARE):
                $this->parseDeclare();
                return;
            case self::matches($token, T_HALT_COMPILER):
                $this->cursor = count($this->significant);
                return;
            case self::matches($token, T_ATTRIBUTE) || self::matches($token, self::CLASS_MODIFIERS) || self::matches($token, self::CLASS_KEYWORDS) || self::matches($token, T_FUNCTION):
                $this->parseDeclarationOrExpression();
                return;
            case self::matches($token, T_IF):
                $this->markExecutable($token, $index);
                $this->parseIf();
                return;
            case self::matches($token, [T_WHILE, T_FOR, T_FOREACH]):
                $this->markExecutable($token, $index);
                $this->parseLoop();
                return;
            case self::matches($token, T_DO):
                $this->markExecutable($token, $index);
                $this->parseDoWhile();
                return;
            case self::matches($token, T_SWITCH):
                $this->markExecutable($token, $index);
                $this->parseSwitch();
                return;
            case self::matches($token, T_TRY):
                $this->parseTry();
                return;
            case self::matches($token, T_STRING) && self::matches($this->peek(1), ':'):
                $this->advance(); // goto label
                $this->advance();
                return;
            default:
                $this->markExecutable($token, $index);
                $this->scanExpression([';']);
                $this->consumeTerminator();
        }
    }

    private function parseBlock(): void
    {
        $this->expect('{');
        $this->parseStatements(['}']);
        $this->expect('}');
    }

    /** The body of a control structure; a single statement gets braces so a probe can join it. */
    private function parseBody(): void
    {
        if ($this->isAt('{')) {
            $this->parseBlock();
            return;
        }

        $start = $this->currentIndex();
        $this->before[$start] = '{ ' . ($this->before[$start] ?? '');
        $this->parseStatement();

        $end = $this->lastConsumed;
        if (self::matches($this->tokens[$end], T_CLOSE_TAG)) {
            // The close tag ended the statement; the brace goes before the tag, so the statement needs its `;`.
            $this->before[$end] = ($this->before[$end] ?? '') . '; } ';
        } else {
            $this->after[$end] = ($this->after[$end] ?? '') . ' }';
        }
    }

    private function parseNamespace(): void
    {
        $this->advance();
        $this->namespace = $this->isAt([T_STRING, T_NAME_QUALIFIED]) ? $this->advance()->text : '';
        if ($this->isAt('{')) {
            $this->parseBlock();
        } else {
            $this->consumeTerminator();
        }
    }

    private function parseDeclare(): void
    {
        $this->advance();
        $this->parenthesized();
        if ($this->isAt(':')) {
            $this->advance();
            $this->parseStatements([T_ENDDECLARE]);
            $this->expect(T_ENDDECLARE);
            $this->consumeTerminator();
        } elseif ($this->isAt(';') || $this->isAt(T_CLOSE_TAG)) {
            $this->consumeTerminator();
        } else {
            $this->parseStatement();
        }
    }

    /** Attributes and modifiers can start a class, a function declaration or an expression (closure). */
    private function parseDeclarationOrExpression(): void
    {
        $start = $this->requireCurrent();
        $startIndex = $this->currentIndex();
        while ($this->isAt(T_ATTRIBUTE)) {
            $this->skipAttribute();
        }

        if ($this->isAt(self::CLASS_MODIFIERS) || $this->isAt(self::CLASS_KEYWORDS)) {
            $this->parseClassLike();
            return;
        }
        if ($this->isAt(T_FUNCTION) && $this->isNamedFunctionAhead()) {
            $this->parseNamedFunction(null);
            return;
        }

        $this->markExecutable($start, $startIndex);
        $this->scanExpression([';']);
        $this->consumeTerminator();
    }

    private function parseIf(): void
    {
        $this->advance();
        $this->parenthesized();

        if ($this->isAt(':')) {
            $this->advance();
            $this->parseStatements([T_ELSEIF, T_ELSE, T_ENDIF]);
            while ($this->isAt(T_ELSEIF)) {
                $this->probedCondition($this->advance());
                $this->expect(':');
                $this->parseStatements([T_ELSEIF, T_ELSE, T_ENDIF]);
            }
            if ($this->isAt(T_ELSE)) {
                $this->advance();
                $this->expect(':');
                $this->parseStatements([T_ENDIF]);
            }
            $this->expect(T_ENDIF);
            $this->consumeTerminator();
            return;
        }

        $this->parseBody();
        while ($this->isAt(T_ELSEIF)) {
            $this->probedCondition($this->advance());
            $this->parseBody();
        }
        if ($this->isAt(T_ELSE)) {
            $this->advance();
            $this->parseBody();
        }
    }

    private function parseLoop(): void
    {
        $keyword = $this->advance();
        $this->parenthesized();

        if ($this->isAt(':')) {
            $end = match ($keyword->id) {
                T_WHILE => T_ENDWHILE,
                T_FOR => T_ENDFOR,
                default => T_ENDFOREACH,
            };
            $this->advance();
            $this->parseStatements([$end]);
            $this->expect($end);
            $this->consumeTerminator();
            return;
        }

        $this->parseBody();
    }

    private function parseDoWhile(): void
    {
        $this->advance();
        $this->parseBody();
        $this->probedCondition($this->expect(T_WHILE));
        $this->consumeTerminator();
    }

    private function parseSwitch(): void
    {
        $this->advance();
        $this->parenthesized();

        $isAlternative = $this->isAt(':');
        $end = $isAlternative ? T_ENDSWITCH : '}';
        $this->advance();

        while ($this->current() !== null && !$this->isAt($end)) {
            if ($this->isAt(T_CASE)) {
                $this->advance();
                $this->scanExpression([':', ';'], isTernaryAware: true);
                $this->advance();
            } elseif ($this->isAt(T_DEFAULT)) {
                $this->advance();
                $this->advance();
            } else {
                $this->parseStatement();
            }
        }

        $this->expect($end);
        if ($isAlternative) {
            $this->consumeTerminator();
        }
    }

    private function parseTry(): void
    {
        $this->advance();
        $this->parseBlock();
        while ($this->isAt(T_CATCH)) {
            $this->advance();
            $this->parenthesized();
            $this->parseBlock();
        }
        if ($this->isAt(T_FINALLY)) {
            $this->advance();
            $this->parseBlock();
        }
    }

    // ---- Functions and classes ---------------------------------------------------------------

    private function parseNamedFunction(?string $className): void
    {
        $keyword = $this->advance();
        if ($this->isAt('&')) {
            $this->advance();
        }
        $name = $this->advance()->text;
        $this->parseParameterList($className);
        $this->skipReturnType();

        if ($this->isAt(';')) {
            $this->advance(); // abstract or interface method
            return;
        }

        $label = match (true) {
            $className !== null => "$className::$name",
            $this->namespace !== '' => "$this->namespace\\$name",
            default => $name,
        };
        $this->parseFunctionBody($label, $keyword->line);
    }

    private function parseClosure(): void
    {
        $this->advance();
        if ($this->isAt('&')) {
            $this->advance();
        }
        $this->parseParameterList();
        if ($this->isAt(T_USE)) {
            $this->advance();
            $this->parenthesized();
        }
        $this->skipReturnType();
        $this->parseBlock();
    }

    private function parseArrowFunction(): void
    {
        $this->advance();
        $returnsReference = $this->isAt('&');
        if ($returnsReference) {
            $this->advance();
        }
        $this->parseParameterList();
        if ($this->isAt(':')) {
            while (!$this->isAt(T_DOUBLE_ARROW)) {
                $this->advance();
            }
        }
        $this->expect(T_DOUBLE_ARROW);

        $bodyStart = $this->requireCurrent();
        $bodyStartIndex = $this->currentIndex();
        $this->scanExpression(self::ARROW_BODY_TERMINATORS, isTernaryAware: true);

        // A ternary wrapper would break returning by reference; such bodies stay unprobed.
        if (!$returnsReference) {
            $this->wrapExpression($bodyStart, $bodyStartIndex, $this->lastConsumed);
        }
    }

    private function parseFunctionBody(string $label, int $startLine): void
    {
        $this->expect('{');
        $this->parseStatements(['}']);
        $closingBrace = $this->expect('}');
        $this->functions[] = ['name' => $label, 'startLine' => $startLine, 'endLine' => $closingBrace->line];
    }

    /**
     * Parameters can hold closures (constant expressions) and property hooks (promoted properties).
     *
     * @param string|null $className the class of a constructor, which may promote properties
     */
    private function parseParameterList(?string $className = null): void
    {
        $this->expect('(');
        $depth = 0;
        $parameter = '';
        while ($this->current() !== null) {
            if ($this->isAt(')') && $depth === 0) {
                $this->advance();
                return;
            }
            if ($this->isAt(T_VARIABLE)) {
                $parameter = $this->current()->text;
            }
            match (true) {
                $this->isAt(T_FUNCTION) => $this->parseClosure(),
                $this->isAt(T_FN) => $this->parseArrowFunction(),
                $this->isAt(T_ATTRIBUTE) => $this->skipAttribute(),
                $this->isAt('{') && $depth === 0 => $this->parsePropertyHooks("$className::$parameter"),
                default => $depth += $this->bracketDelta($this->advance()),
            };
        }
    }

    private function parseClassLike(): void
    {
        while ($this->isAt(self::CLASS_MODIFIERS) || $this->isAt(T_ATTRIBUTE)) {
            $this->isAt(T_ATTRIBUTE) ? $this->skipAttribute() : $this->advance();
        }
        $this->advance(); // class, interface, trait or enum
        $name = $this->isAt(T_STRING) ? $this->advance()->text : 'class@anonymous';
        while (!$this->isAt('{')) {
            $this->advance(); // extends, implements, backed enum type
        }

        $this->parseClassBody($this->namespace === '' ? $name : "$this->namespace\\$name");
    }

    private function parseAnonymousClass(): void
    {
        $this->advance(); // new
        while (!$this->isAt(T_CLASS)) {
            $this->isAt(T_ATTRIBUTE) ? $this->skipAttribute() : $this->advance();
        }
        $this->advance();
        if ($this->isAt('(')) {
            $this->parenthesized();
        }
        while (!$this->isAt('{')) {
            $this->advance();
        }

        $this->parseClassBody('class@anonymous');
    }

    private function parseClassBody(string $className): void
    {
        $this->expect('{');
        while ($this->current() !== null && !$this->isAt('}')) {
            match (true) {
                $this->isAt(T_ATTRIBUTE) => $this->skipAttribute(),
                $this->isAt(T_USE) => $this->skipTraitUse(),
                $this->isAt([T_CASE, T_CONST]) => $this->skipConstantLike(),
                $this->isAt(self::MEMBER_MODIFIERS) => $this->advance(),
                $this->isAt(T_FUNCTION) => $this->parseNamedFunction($className),
                default => $this->parseProperty($className),
            };
        }
        $this->expect('}');
    }

    private function parseProperty(string $className): void
    {
        $property = '';
        while ($this->current() !== null) {
            if ($this->isAt(T_VARIABLE)) {
                $property = $this->advance()->text;
                if ($this->isAt('=')) {
                    $this->advance();
                    $this->scanExpression([';', ',', '{']);
                }
                continue;
            }
            if ($this->isAt('{')) {
                $this->parsePropertyHooks("$className::$property");
                return;
            }
            if ($this->isAt(';')) {
                $this->advance();
                return;
            }
            $this->advance(); // type tokens and commas between properties
        }
    }

    private function parsePropertyHooks(string $propertyLabel): void
    {
        $this->expect('{');
        while ($this->current() !== null && !$this->isAt('}')) {
            if ($this->isAt(T_ATTRIBUTE)) {
                $this->skipAttribute();
                continue;
            }
            if ($this->isAt(self::MEMBER_MODIFIERS) || $this->isAt('&')) {
                $this->advance();
                continue;
            }

            $hook = $this->advance(); // get or set
            if ($this->isAt('(')) {
                $this->parseParameterList();
            }

            if ($this->isAt(T_DOUBLE_ARROW)) {
                $this->advance();
                $bodyStart = $this->requireCurrent();
                $bodyStartIndex = $this->currentIndex();
                $this->scanExpression([';']);
                $this->wrapExpression($bodyStart, $bodyStartIndex, $this->lastConsumed);
                $this->expect(';');
            } elseif ($this->isAt('{')) {
                $this->parseFunctionBody("$propertyLabel::$hook->text", $hook->line);
            } else {
                $this->expect(';'); // abstract hook
            }
        }
        $this->expect('}');
    }

    // ---- Expressions -------------------------------------------------------------------------

    /**
     * Consumes a balanced run of tokens up to (not including) a terminator at bracket depth 0.
     * Closures, arrow functions and anonymous classes inside are parsed so their bodies get probes.
     *
     * @param list<int|string> $terminators
     */
    private function scanExpression(array $terminators, bool $isTernaryAware = false): void
    {
        // The hottest loop of the instrumenter, so it compares token ids directly.
        $stopIds = [];
        foreach ([...$terminators, ...self::CLOSING_BRACKETS] as $kind) {
            $stopIds[is_int($kind) ? $kind : ord($kind)] = true; // a closing bracket ends a construct outside this expression
        }
        if (in_array(';', $terminators, true)) {
            $stopIds[T_CLOSE_TAG] = true;
        }
        $questionMark = ord('?');
        $colon = ord(':');
        $depth = 0;
        $openTernaries = 0;

        while (($token = $this->current()) !== null) {
            $id = $token->id;
            if ($depth === 0) {
                if ($isTernaryAware && $id === $questionMark) {
                    $openTernaries++;
                    $this->advance();
                    continue;
                }
                if ($isTernaryAware && $id === $colon && $openTernaries > 0) {
                    $openTernaries--;
                    $this->advance();
                    continue;
                }
                if (isset($stopIds[$id])) {
                    return;
                }
            }

            match (true) {
                $id === T_FUNCTION => $this->parseClosure(),
                $id === T_FN => $this->parseArrowFunction(),
                $id === T_NEW && $this->isAnonymousClassAhead() => $this->parseAnonymousClass(),
                $id === T_ATTRIBUTE => $this->skipAttribute(),
                default => $depth += $this->bracketDelta($this->advance()),
            };
        }
    }

    /** A condition that runs separately from its statement gets a probe inside the parentheses. */
    private function probedCondition(\PhpToken $keyword): void
    {
        $this->executableLines[$keyword->line] = true;
        $open = $this->currentIndex();
        $this->expect('(');
        $this->scanExpression([')']);
        $close = $this->currentIndex();
        $this->expect(')');

        $this->after[$open] = ($this->after[$open] ?? '') . '(' . $this->probe . "[$keyword->line]) && (";
        $this->before[$close] = ($this->before[$close] ?? '') . ')';
    }

    /** Wraps an expression so it counts when it is evaluated: ((++probe) ? (expr) : null). */
    private function wrapExpression(\PhpToken $first, int $firstIndex, int $lastIndex): void
    {
        $this->executableLines[$first->line] = true;
        $this->before[$firstIndex] = '((' . $this->probe . "[$first->line])?(" . ($this->before[$firstIndex] ?? '');
        $this->after[$lastIndex] = ($this->after[$lastIndex] ?? '') . '):null)';
    }

    private function parenthesized(): void
    {
        $this->expect('(');
        $this->scanExpression([')']);
        $this->expect(')');
    }

    // ---- Small helpers -----------------------------------------------------------------------

    private function markExecutable(\PhpToken $token, int $index): void
    {
        $this->executableLines[$token->line] = true;
        $this->before[$index] = ($this->before[$index] ?? '') . $this->probeStatement($token->line) . ' ';
    }

    private function probeStatement(int $line): string
    {
        return $this->probe . "[$line];";
    }

    private function skipAttribute(): void
    {
        $this->advance(); // #[
        $depth = 1;
        while ($depth > 0 && $this->current() !== null) {
            $token = $this->advance();
            $depth += match (true) {
                self::matches($token, '[') => 1,
                self::matches($token, ']') => -1,
                default => 0,
            };
        }
    }

    private function skipTraitUse(): void
    {
        $this->advance();
        while (!$this->isAt([';', '{'])) {
            $this->advance();
        }
        if ($this->isAt(';')) {
            $this->advance();
            return;
        }

        $depth = 0;
        do {
            $depth += $this->bracketDelta($this->advance());
        } while ($depth > 0);
    }

    private function skipConstantLike(): void
    {
        $this->advance();
        $this->scanExpression([';']);
        $this->expect(';');
    }

    private function skipReturnType(): void
    {
        if (!$this->isAt(':')) {
            return;
        }
        while (!$this->isAt(['{', ';', T_DOUBLE_ARROW])) {
            $this->advance();
        }
    }

    private function consumeTerminator(): void
    {
        if ($this->isAt([';', T_CLOSE_TAG])) {
            $this->advance();
        }
    }

    private function isNamedFunctionAhead(): bool
    {
        $offset = self::matches($this->peek(1), '&') ? 2 : 1;

        return self::matches($this->peek($offset), T_STRING);
    }

    /** `new class`, `new readonly class` or `new #[Attribute] class`. */
    private function isAnonymousClassAhead(): bool
    {
        $offset = 1;
        while (($token = $this->peek($offset)) !== null) {
            if (self::matches($token, T_ATTRIBUTE)) {
                $offset = $this->offsetAfterAttribute($offset);
            } elseif (self::matches($token, T_READONLY)) {
                $offset++;
            } else {
                return self::matches($token, T_CLASS);
            }
        }

        return false;
    }

    private function offsetAfterAttribute(int $offset): int
    {
        $depth = 0;
        do {
            $token = $this->peek($offset++);
            $depth += match (true) {
                $token === null => -$depth,
                self::matches($token, T_ATTRIBUTE), self::matches($token, '[') => 1,
                self::matches($token, ']') => -1,
                default => 0,
            };
        } while ($depth > 0);

        return $offset;
    }

    private function bracketDelta(\PhpToken $token): int
    {
        return match (true) {
            self::matches($token, self::OPENING_BRACKETS) => 1,
            self::matches($token, self::CLOSING_BRACKETS) => -1,
            default => 0,
        };
    }

    private function current(): ?\PhpToken
    {
        return isset($this->significant[$this->cursor]) ? $this->tokens[$this->significant[$this->cursor]] : null;
    }

    private function requireCurrent(): \PhpToken
    {
        return $this->current() ?? throw new InstrumentationException('Unexpected end of file.');
    }

    private function currentIndex(): int
    {
        return $this->significant[$this->cursor] ?? count($this->tokens);
    }

    private function peek(int $offset): ?\PhpToken
    {
        $index = $this->significant[$this->cursor + $offset] ?? null;

        return $index === null ? null : $this->tokens[$index];
    }

    /** @param int|string|list<int|string> $kind */
    private function isAt(int|string|array $kind): bool
    {
        return self::matches($this->current(), $kind);
    }

    private function advance(): \PhpToken
    {
        $token = $this->requireCurrent();
        $this->lastConsumed = $this->significant[$this->cursor];
        $this->cursor++;

        return $token;
    }

    private function expect(int|string $kind): \PhpToken
    {
        if (!$this->isAt($kind)) {
            $found = $this->current();
            $expected = is_int($kind) ? token_name($kind) : "'$kind'";
            throw new InstrumentationException(sprintf(
                'Expected %s on line %d, found %s.',
                $expected,
                $found->line ?? 0,
                $found === null ? 'the end of the file' : "'$found->text'",
            ));
        }

        return $this->advance();
    }

    /**
     * PhpToken::is() compares text, so a string fragment ")" would look like a bracket. Single
     * characters are therefore matched by token id, which PHP sets to the character code.
     *
     * @param int|string|list<int|string> $kind
     */
    private static function matches(?\PhpToken $token, int|string|array $kind): bool
    {
        if ($token === null) {
            return false;
        }
        $id = $token->id;
        if (!is_array($kind)) {
            return $id === (is_int($kind) ? $kind : ord($kind));
        }
        foreach ($kind as $candidate) {
            if ($id === (is_int($candidate) ? $candidate : ord($candidate))) {
                return true;
            }
        }

        return false;
    }

    private function render(): string
    {
        $code = '';
        foreach ($this->tokens as $index => $token) {
            $code .= ($this->before[$index] ?? '') . ($this->replace[$index] ?? $token->text) . ($this->after[$index] ?? '');
        }

        return $code;
    }
}
