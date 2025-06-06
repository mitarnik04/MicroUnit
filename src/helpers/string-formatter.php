<?php
require_once __DIR__ . '/diff.php';
require_once __DIR__ . '/value-exporter.php';

class StringFormatter
{
    public static function formatLabelledBlock(string $text): string
    {

        $lines = explode("\n", $text);
        $formattedLines = [];

        $firstLine = $lines[0];
        $colonPos = strpos($firstLine, ':');
        // Add 2 for ': ' spacing
        $indentation = $colonPos !== false ? $colonPos + 2 : 4;

        $formattedLines[] = $firstLine;

        // Process subsequent lines
        for ($i = 1; $i < count($lines); $i++) {
            $line = $lines[$i];
            $formattedLines[] = str_repeat(' ', $indentation) . $line;
        }

        return implode("\n", $formattedLines);
    }
}
