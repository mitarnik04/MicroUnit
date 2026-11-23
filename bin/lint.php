<?php

/**
 * Checks MicroUnit's own sources: valid PHP 8.5 syntax plus the structural rules from
 * CONTRIBUTING.md#code-style that no formatter can enforce, and that the docs keep up with the code.
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
        $tokens = array_values(PhpToken::tokenize($code, TOKEN_PARSE));
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

array_push($problems, ...checkDocs($root));

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
 * The docs are written from the source; this is the safety net for a slip. Every CLI option, config
 * method and public assertion must appear on its page, and every link between the pages, from the
 * navigation and from the README must lead somewhere. It checks completeness, not wording.
 *
 * @return list<string>
 */
function checkDocs(string $root): array
{
    require_once "$root/src/Bootstrap/Autoloader.php";
    MicroUnit\Bootstrap\Autoloader::register();

    $cliPage = (string) file_get_contents("$root/docs/cli.md");
    preg_match_all('~`(--[a-z][a-z-]*)~', $cliPage, $documentedOptions);
    $configPage = (string) file_get_contents("$root/docs/configuration.md");
    preg_match_all('~^#{2,4} `([a-zA-Z]+)\(~m', $configPage, $documentedMethods);
    $assertionsPage = (string) file_get_contents("$root/docs/assertions.md");
    $undocumentedAssertions = array_filter(publicAssertions(), static fn(string $name): bool => preg_match("~`(Assert::)?$name\\b~", $assertionsPage) !== 1);

    // The options `microunit help` lists must be documented; any option the page mentions must be one the
    // command line accepts (that also covers --help and --version, and leaves out internal ones).
    preg_match_all("~'(--[a-z][a-z-]*)'~", (string) file_get_contents("$root/src/Cli/CliOptions.php"), $acceptedOptions);

    return [
        ...compareWithDocs('CLI option', helpOptions(), $acceptedOptions[1], array_values(array_unique($documentedOptions[1])), 'docs/cli.md'),
        ...compareWithDocs('config method', configMethods(), configMethods(), $documentedMethods[1], 'docs/configuration.md'),
        ...array_map(static fn(string $name): string => "docs/assertions.md  does not document Assert::$name()", array_values($undocumentedAssertions)),
        ...brokenLinks($root),
    ];
}

/**
 * @param list<string> $required names the page must document
 * @param list<string> $existing names that exist in the code
 * @param list<string> $inDocs names the page documents
 * @return list<string>
 */
function compareWithDocs(string $kind, array $required, array $existing, array $inDocs, string $page): array
{
    return [
        ...array_map(static fn(string $name): string => "$page  does not document the $kind $name", array_values(array_diff($required, $inDocs))),
        ...array_map(static fn(string $name): string => "$page  documents the $kind $name, which does not exist", array_values(array_diff($inDocs, $existing))),
    ];
}

/**
 * Every option of every command, read from the data behind `microunit help`.
 *
 * @return list<string>
 */
function helpOptions(): array
{
    /** @var array<string, array{string, string, string, array<string, list<array{string, string}>>, list<string>, string}> $commands see HelpText::COMMANDS */
    $commands = new ReflectionClassConstant(MicroUnit\Cli\HelpText::class, 'COMMANDS')->getValue();
    $options = [];
    foreach ($commands as [, , , $groups]) {
        foreach ($groups as $group) {
            foreach ($group as [$option]) {
                preg_match_all('~--[a-z][a-z-]*~', $option, $match);
                array_push($options, ...$match[0]);
            }
        }
    }

    return array_values(array_unique($options));
}

/** @return list<string> */
function configMethods(): array
{
    $methods = new ReflectionClass(MicroUnit\Config\MicroUnitConfigBuilder::class)->getMethods(ReflectionMethod::IS_PUBLIC);
    $methods = array_filter($methods, static fn(ReflectionMethod $method): bool => !$method->isStatic() && $method->name !== 'build');

    return array_values(array_map(static fn(ReflectionMethod $method): string => $method->name, $methods));
}

/** @return list<string> the public Assert methods, without the ones marked @internal */
function publicAssertions(): array
{
    $methods = new ReflectionClass(MicroUnit\Assertion\Assert::class)->getMethods(ReflectionMethod::IS_PUBLIC);
    $methods = array_filter($methods, static fn(ReflectionMethod $method): bool => !str_contains((string) $method->getDocComment(), '@internal'));

    return array_values(array_unique(array_map(static fn(ReflectionMethod $method): string => $method->name, $methods)));
}


/**
 * Links that lead nowhere: pages, #anchors and navigation entries of the docs site, and the files and
 * docs pages the README points to.
 *
 * @return list<string>
 */
function brokenLinks(string $root): array
{
    $site = 'https://mitarnik04.github.io/MicroUnit/';
    $pageOf = static fn(string $slug): string => ($slug === '' ? 'index' : $slug) . '.md';
    $problems = [];

    $sources = [...glob("$root/docs/*.md") ?: [], "$root/README.md"];
    foreach ($sources as $source) {
        $name = str_replace('\\', '/', substr($source, strlen($root) + 1));
        // {{ '/page/' | relative_url }} is how the layout-free pages link; read it as the full URL.
        $content = (string) preg_replace("~\{\{ '/([^']*)' \| relative_url \}\}~", "$site\$1", (string) file_get_contents($source));
        preg_match_all('~\]\(([^)\s]+)\)|(?:href|src|srcset)="([^"]+)"~', $content, $matches, PREG_SET_ORDER);
        $links = array_map(static fn(array $match): string => ($match[1] ?? '') !== '' ? $match[1] : ($match[2] ?? ''), $matches);

        foreach ($links as $link) {
            [$target, $anchor] = explode('#', $link, 2) + [1 => ''];
            if (str_starts_with($target, $site)) {
                $path = trim(substr($target, strlen($site)), '/');
                $page = str_contains($path, '.') ? $path : $pageOf($path);
            } elseif (preg_match('~^[a-z][a-z0-9+.-]*:~i', $target) === 1) {
                continue; // another website, mailto: and the like
            } elseif ($target === '') {
                $page = basename($name);
            } else {
                $page = $target;
            }
            $file = str_starts_with($target, $site) || str_starts_with($name, 'docs/') || $target === '' ? "$root/docs/$page" : "$root/$page";
            if (!is_file($file)) {
                $problems[] = "$name  links to $link, which does not exist";
            } elseif ($anchor !== '' && str_ends_with($file, '.md') && !in_array($anchor, headingIds((string) file_get_contents($file)), true)) {
                $problems[] = "$name  links to $link, but that page has no heading #$anchor";
            }
        }
    }

    preg_match_all('~url: (/[^ ,}]*)~', (string) file_get_contents("$root/docs/_data/navigation.yml"), $navigation);
    $navigationPages = array_map(static fn(string $url): string => $pageOf(trim($url, '/')), $navigation[1]);
    foreach ($navigationPages as $page) {
        if (!is_file("$root/docs/$page")) {
            $problems[] = "docs/_data/navigation.yml  lists docs/$page, which does not exist";
        }
    }
    foreach (glob("$root/docs/*.md") ?: [] as $page) {
        if (!in_array(basename($page), $navigationPages, true)) {
            $problems[] = 'docs/' . basename($page) . '  is missing from docs/_data/navigation.yml';
        }
    }

    return $problems;
}

/**
 * The ids GitHub Pages gives the headings of a page (kramdown with GFM input): lower case, without
 * punctuation, a hyphen for every space.
 *
 * @return list<string>
 */
function headingIds(string $markdown): array
{
    $markdown = (string) preg_replace('~^```.*?^```~ms', '', $markdown);
    preg_match_all('~^#{1,6} (.+)$~m', $markdown, $headings);

    return array_map(static fn(string $heading): string => str_replace(' ', '-', (string) preg_replace('~[^\p{L}\p{N}_ -]~u', '', mb_strtolower(trim($heading)))), $headings[1]);
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
        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if ($file->getExtension() === 'php' || $file->getFilename() === 'microunit') {
                $files[] = $file->getPathname();
            }
        }
    }
    sort($files);

    return $files;
}
