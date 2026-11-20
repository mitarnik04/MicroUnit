<?php

namespace MicroUnit\Coverage\Report;

use MicroUnit\Coverage\CoverageData;
use MicroUnit\Helpers\Filesystem;

/** Cobertura XML, used by GitLab merge request coverage and Azure DevOps. */
final class CoberturaCoverageReport
{
    private function __construct() {}

    public static function write(CoverageData $data, string $file, string $baseDirectory): void
    {
        $base = rtrim(str_replace('\\', '/', $baseDirectory), '/') . '/';
        $packages = [];
        foreach (array_keys($data->files) as $path) {
            $relative = str_replace('\\', '/', $path);
            $relative = str_starts_with($relative, $base) ? substr($relative, strlen($base)) : ltrim($relative, '/');
            $packages[dirname($relative)][$relative] = $path;
        }

        $totals = $data->totals();
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<!DOCTYPE coverage SYSTEM "http://cobertura.sourceforge.net/xml/coverage-04.dtd">' . "\n"
            . sprintf(
                '<coverage line-rate="%s" branch-rate="0" lines-covered="%d" lines-valid="%d" branches-covered="0" branches-valid="0" complexity="0" version="0.1" timestamp="%d">',
                self::rate($totals['coveredLines'], $totals['executableLines']),
                $totals['coveredLines'],
                $totals['executableLines'],
                time(),
            ) . "\n"
            . '  <sources><source>' . Xml::escape(rtrim($base, '/')) . "</source></sources>\n"
            . "  <packages>\n";

        foreach ($packages as $package => $files) {
            [$covered, $valid] = [0, 0];
            $classes = '';
            foreach ($files as $relative => $path) {
                $fileTotals = $data->fileTotals($path);
                $covered += $fileTotals['coveredLines'];
                $valid += $fileTotals['executableLines'];
                $classes .= sprintf(
                    "        <class name=\"%s\" filename=\"%s\" line-rate=\"%s\" branch-rate=\"0\" complexity=\"0\">\n          <methods/>\n          <lines>\n",
                    Xml::escape(basename($relative, '.php')),
                    Xml::escape($relative),
                    self::rate($fileTotals['coveredLines'], $fileTotals['executableLines']),
                );
                foreach ($data->files[$path]['lines'] as $line => $hits) {
                    $classes .= "            <line number=\"$line\" hits=\"$hits\" branch=\"false\"/>\n";
                }
                $classes .= "          </lines>\n        </class>\n";
            }
            $xml .= sprintf("    <package name=\"%s\" line-rate=\"%s\" branch-rate=\"0\" complexity=\"0\">\n      <classes>\n", Xml::escape(str_replace('/', '.', $package)), self::rate($covered, $valid))
                . $classes
                . "      </classes>\n    </package>\n";
        }

        $xml .= "  </packages>\n</coverage>\n";
        Filesystem::writeAtomically($file, $xml);
    }

    private static function rate(int $covered, int $total): string
    {
        return $total === 0 ? '1' : rtrim(rtrim(number_format($covered / $total, 4, '.', ''), '0'), '.');
    }
}
