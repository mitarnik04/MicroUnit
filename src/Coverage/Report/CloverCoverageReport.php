<?php

namespace MicroUnit\Coverage\Report;

use MicroUnit\Coverage\CoverageData;
use MicroUnit\Helpers\Filesystem;

/** Clover XML, read by Codecov, Coveralls, Jenkins, SonarQube and most coverage services. */
final class CloverCoverageReport
{
    private function __construct() {}

    public static function write(CoverageData $data, string $file): void
    {
        $timestamp = time();
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . "<coverage generated=\"$timestamp\">\n"
            . "  <project timestamp=\"$timestamp\" name=\"MicroUnit\">\n";

        foreach (array_keys($data->files) as $path) {
            $totals = $data->fileTotals($path);
            $xml .= '    <file name="' . Xml::escape($path) . "\">\n";
            foreach ($data->functionsOf($path) as $function) {
                $xml .= sprintf(
                    "      <line num=\"%d\" type=\"method\" name=\"%s\" visibility=\"public\" complexity=\"1\" crap=\"1\" count=\"%d\"/>\n",
                    $function['startLine'],
                    Xml::escape(ReportNames::shortFunctionName($function['name'])),
                    $function['hits'],
                );
            }
            foreach ($data->files[$path]['lines'] as $line => $hits) {
                $xml .= "      <line num=\"$line\" type=\"stmt\" count=\"$hits\"/>\n";
            }
            $xml .= '      ' . self::metrics($totals) . "\n    </file>\n";
        }

        $xml .= '    ' . self::metrics($data->totals(), count($data->files)) . "\n  </project>\n</coverage>\n";
        Filesystem::writeAtomically($file, $xml);
    }

    /** @param array{executableLines: int, coveredLines: int, functions: int, coveredFunctions: int} $totals */
    private static function metrics(array $totals, ?int $fileCount = null): string
    {
        return sprintf(
            '<metrics %sloc="%d" ncloc="%d" statements="%d" coveredstatements="%d" methods="%d" coveredmethods="%d" conditionals="0" coveredconditionals="0" elements="%d" coveredelements="%d"/>',
            $fileCount === null ? '' : "files=\"$fileCount\" ",
            $totals['executableLines'],
            $totals['executableLines'],
            $totals['executableLines'],
            $totals['coveredLines'],
            $totals['functions'],
            $totals['coveredFunctions'],
            $totals['executableLines'] + $totals['functions'],
            $totals['coveredLines'] + $totals['coveredFunctions'],
        );
    }
}
