<?php

namespace MicroUnit\Explorer;

/** Turns the configured editor into a URL template for "open file at line" links. */
final class EditorLink
{
    private const array TEMPLATES = [
        'vscode' => 'vscode://file/{file}:{line}',
        'cursor' => 'cursor://file/{file}:{line}',
        'windsurf' => 'windsurf://file/{file}:{line}',
        'zed' => 'zed://file/{file}:{line}',
        'phpstorm' => 'phpstorm://open?file={file}&line={line}',
        'idea' => 'idea://open?file={file}&line={line}',
        'sublime' => 'subl://open?url=file://{file}&line={line}',
    ];

    private function __construct() {}

    /** @param string $editor a known editor name or a custom template containing {file} */
    public static function template(string $editor): string
    {
        return self::TEMPLATES[strtolower($editor)] ?? (str_contains($editor, '{file}') ? $editor : self::TEMPLATES['vscode']);
    }
}
