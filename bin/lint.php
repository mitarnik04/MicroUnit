<?php

/**
 * Checks MicroUnit's own sources: valid PHP 8.5 syntax plus the structural rules from
 * CONTRIBUTING.md#code-style that no formatter can enforce.
 *
 *     php bin/lint.php
 *
 * Formatting (spacing, braces, imports) is PHP-CS-Fixer's job, see .php-cs-fixer.dist.php.
 */

$root = dirname(__DIR__);
$problems = [];

foreach (phpFiles($root, ['src', 'tests', 'bin', 'assets/brand']) as $file) {
    $relative = str_replace('\\', '/', substr($file, strlen($root) + 1));
    $code = (string) file_get_contents($file);

    try {
        $tokens = PhpToken::tokenize($code, TOKEN_PARSE);
    } catch (ParseError $error) {
        $problems[] = "$relative:{$error->getLine()}  syntax error: {$error->getMessage()}";
        continue;
    }

    if (str_contains($code, "\r\n")) {
        $problems[] = "$relative  uses CRLF line endings (LF expected, see .editorconfig)";
    }
    if (preg_match('/[ \t]+$/m', $code, $match, PREG_OFFSET_CAPTURE) === 1) {
        $problems[] = "$relative:" . (substr_count($code, "\n", 0, $match[0][1]) + 1) . '  trailing whitespace';
    }

    if (str_starts_with($relative, 'src/')) {
        array_push($problems, ...checkSourceStructure($relative, $tokens));
    }
}

// The docs carry a copy of the brand tokens (GitHub Pages only serves docs/); it must stay identical.
foreach (['resources/brand.css' => 'docs/assets/css/brand.css'] as $original => $copy) {
    if (@file_get_contents("$root/$original") !== @file_get_contents("$root/$copy")) {
        $problems[] = "$copy  differs from $original (copy it over)";
    }
}

if ($problems === []) {
    echo '✔ Lint passed.' . PHP_EOL;
    exit(0);
}

echo implode(PHP_EOL, $problems) . PHP_EOL . PHP_EOL . '✖ ' . count($problems) . ' problem(s) found.' . PHP_EOL;
exit(1);

/**
 * PSR-4 and naming rules for src/: one class-like per file, named after the file, in the namespace
 * that mirrors its folder; interfaces start with "I".
 *
 * @param list<PhpToken> $tokens
 * @return list<string>
 */
function checkSourceStructure(string $relative, array $tokens): array
{
    $problems = [];
    $expectedName = basename($relative, '.php');
    $expectedNamespace = 'MicroUnit' . str_replace('/', '\\', substr(dirname($relative), strlen('src')));
    $declarations = declarations($tokens);

    if (count($declarations) !== 1) {
        return ["$relative  must declare exactly one class, interface, trait or enum (found " . count($declarations) . ')'];
    }

    [$kind, $name] = $declarations[0];
    if ($name !== $expectedName) {
        $problems[] = "$relative  declares $kind $name, but the file name requires $expectedName (PSR-4)";
    }
    if ($kind === 'interface' && preg_match('/^I[A-Z]/', $name) !== 1) {
        $problems[] = "$relative  interface $name must be prefixed with I (e.g. I$name)";
    }

    $namespace = namespaceOf($tokens);
    if ($namespace !== $expectedNamespace) {
        $problems[] = "$relative  namespace is '$namespace', expected '$expectedNamespace' (PSR-4)";
    }

    return $problems;
}

/**
 * @param list<PhpToken> $tokens
 * @return list<array{string, string}> [kind, name] of top-level class-likes
 */
function declarations(array $tokens): array
{
    $declarations = [];
    foreach ($tokens as $index => $token) {
        if (!$token->is([T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM])) {
            continue;
        }
        // Skip `Foo::class` and anonymous classes (`new class`).
        $previous = previousMeaningful($tokens, $index);
        if ($previous !== null && $previous->is([T_DOUBLE_COLON, T_NEW])) {
            continue;
        }
        $name = nextMeaningful($tokens, $index);
        if ($name !== null && $name->is(T_STRING)) {
            $declarations[] = [strtolower($token->text), $name->text];
        }
    }

    return $declarations;
}

/** @param list<PhpToken> $tokens */
function namespaceOf(array $tokens): string
{
    foreach ($tokens as $index => $token) {
        if ($token->is(T_NAMESPACE)) {
            return (string) nextMeaningful($tokens, $index)?->text;
        }
    }

    return '';
}

/** @param list<PhpToken> $tokens */
function nextMeaningful(array $tokens, int $index): ?PhpToken
{
    for ($i = $index + 1, $count = count($tokens); $i < $count; $i++) {
        if (!$tokens[$i]->isIgnorable()) {
            return $tokens[$i];
        }
    }

    return null;
}

/** @param list<PhpToken> $tokens */
function previousMeaningful(array $tokens, int $index): ?PhpToken
{
    for ($i = $index - 1; $i >= 0; $i--) {
        if (!$tokens[$i]->isIgnorable()) {
            return $tokens[$i];
        }
    }

    return null;
}

/**
 * @param list<string> $directories relative to $root
 * @return list<string>
 */
function phpFiles(string $root, array $directories): array
{
    $files = [];
    foreach ($directories as $directory) {
        $path = $root . '/' . $directory;
        if (!is_dir($path)) {
            continue;
        }
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if ($file->getExtension() === 'php' || $file->getFilename() === 'microunit') {
                $files[] = $file->getPathname();
            }
        }
    }
    sort($files);

    return $files;
}
