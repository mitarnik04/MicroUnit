<?php

/**
 * Code formatting for MicroUnit. PHP-CS-Fixer runs as a standalone PHAR and is never a Composer
 * dependency; see CONTRIBUTING.md#formatting.
 */

$directories = array_filter(
    [__DIR__ . '/src', __DIR__ . '/tests', __DIR__ . '/bin', __DIR__ . '/assets/brand'],
    is_dir(...),
);

$finder = new PhpCsFixer\Finder()
    ->in($directories)
    ->name(['*.php', 'microunit'])
    ->exclude(['Fixtures/projects'])
    ->append([__FILE__, __DIR__ . '/microunit.config.php']);

return new PhpCsFixer\Config()
    ->setRiskyAllowed(true)
    ->setRules([
        '@PSR12' => true,
        '@PHP84Migration' => true,
        'array_syntax' => ['syntax' => 'short'],
        'no_unused_imports' => true,
        'ordered_imports' => ['sort_algorithm' => 'alpha', 'imports_order' => ['class', 'function', 'const']],
        'single_quote' => true,
        'trailing_comma_in_multiline' => ['elements' => ['arrays', 'arguments', 'parameters', 'match']],
        'no_extra_blank_lines' => true,
        'no_whitespace_in_blank_line' => true,
        'class_attributes_separation' => ['elements' => ['method' => 'one', 'property' => 'one', 'const' => 'one']],
        'single_line_empty_body' => true,
        'nullable_type_declaration_for_default_null_value' => true,
        'function_declaration' => ['closure_fn_spacing' => 'none'],
    ])
    ->setFinder($finder)
    ->setCacheFile(__DIR__ . '/.microunit/php-cs-fixer.cache');
