<?php

namespace MicroUnit\Helpers;

/**
 * Shared pieces of every HTML page MicroUnit produces (explorer, test report, coverage report):
 * the brand mark, the embedded fonts and the page templates in resources/. Everything is inlined,
 * so each page is a single file that works offline.
 */
final class HtmlAssets
{
    private const string RESOURCES = __DIR__ . '/../../resources';

    /** [family, file, weight]: static Archivo cuts at the identity's exact widths. */
    private const array FONTS = [
        ['Archivo Wide', 'archivo-w112-800.woff2', 800],
        ['Archivo Condensed', 'archivo-w75-700.woff2', 700],
        ['Archivo Condensed', 'archivo-w75-600.woff2', 600],
        ['Archivo', 'archivo-w100-400.woff2', 400],
        ['Archivo', 'archivo-w100-700.woff2', 700],
    ];

    private function __construct() {}

    /** The "Isolated Unit" mark; colours come from the --mark-block / --mark-unit custom properties. */
    public static function mark(string $class = 'mark'): string
    {
        return '<svg class="' . $class . '" viewBox="0 0 64 64" aria-hidden="true" focusable="false">'
            . '<path fill="var(--mark-block, #0A17A8)" d="M7 12H33a3 3 0 0 1 3 3V28H49a3 3 0 0 1 3 3V57a3 3 0 0 1-3 3H7a3 3 0 0 1-3-3V15a3 3 0 0 1 3-3z"/>'
            . '<rect fill="var(--mark-unit, #3FC6F3)" x="44" y="4" width="16" height="16" rx="3"/></svg>';
    }

    /** A favicon data URI with the unit in the given colour (status lamps in the browser tab). */
    public static function faviconDataUri(string $unitColor = '#3FC6F3'): string
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"><rect fill="#0A17A8" width="64" height="64" rx="14"/>'
            . '<g transform="translate(9.6 9.6) scale(.7)"><path fill="#fff" d="M7 12H33a3 3 0 0 1 3 3V28H49a3 3 0 0 1 3 3V57a3 3 0 0 1-3 3H7a3 3 0 0 1-3-3V15a3 3 0 0 1 3-3z"/>'
            . '<rect fill="' . $unitColor . '" x="44" y="4" width="16" height="16" rx="3"/></g></svg>';

        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }

    /** Fonts plus the shared design tokens (resources/brand.css), ready for a <style> element. */
    public static function baseStyles(): string
    {
        return self::fontFaces() . "\n" . file_get_contents(self::RESOURCES . '/brand.css');
    }

    /** The contents of a file in resources/, e.g. a page-specific stylesheet. */
    public static function resource(string $name): string
    {
        return (string) file_get_contents(self::RESOURCES . '/' . $name);
    }

    /** @font-face rules with the fonts embedded as data URIs. */
    public static function fontFaces(): string
    {
        $css = '';
        foreach (self::FONTS as [$family, $file, $weight]) {
            $data = base64_encode((string) file_get_contents(self::RESOURCES . '/fonts/' . $file));
            $css .= sprintf(
                '@font-face{font-family:"%s";src:url(data:font/woff2;base64,%s) format("woff2");font-weight:%d;font-display:block}',
                $family,
                $data,
                $weight,
            );
        }

        return $css;
    }

    /**
     * Loads a page template from resources/ and fills its {{placeholders}}.
     *
     * @param array<string, string> $values placeholder => already escaped/encoded content
     */
    public static function render(string $template, array $values): string
    {
        $html = (string) file_get_contents(self::RESOURCES . '/' . $template);
        $replacements = ['{{STYLES}}' => self::baseStyles(), '{{MARK}}' => self::mark()];
        foreach ($values as $key => $value) {
            $replacements['{{' . $key . '}}'] = $value;
        }

        return strtr($html, $replacements);
    }

    public static function escape(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /** JSON that is safe to embed inside a <script type="application/json"> element. */
    public static function json(mixed $data): string
    {
        return json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR);
    }
}
