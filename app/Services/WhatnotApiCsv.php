<?php

namespace App\Services;

use RuntimeException;

/**
 * Read a Seller API report CSV into rows keyed by column name. The column
 * row is found within the first lines (a report may start with a title
 * line); empty cells become null.
 */
final class WhatnotApiCsv
{
    /**
     * @param list<string> $required columns that identify the header row
     * @return list<array<string,string|null>>
     */
    public static function rows(string $csv, array $required): array
    {
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, preg_replace('/^\xEF\xBB\xBF/', '', $csv));
        rewind($handle);
        $header = null;
        for ($i = 0; $i < 3 && ($line = fgetcsv($handle, escape: '\\')) !== false; $i++) {
            $candidate = array_map(fn ($h) => strtolower(trim((string) $h)), (array) $line);
            if (array_diff($required, $candidate) === []) {
                $header = $candidate;
                break;
            }
        }
        if ($header === null) {
            fclose($handle);
            if (trim($csv) === '') return [];
            throw new RuntimeException('Unexpected report format: missing '.implode(', ', $required).'.');
        }
        $rows = [];
        while (($line = fgetcsv($handle, escape: '\\')) !== false) {
            if ($line === [null] || $line === []) continue;
            $line = array_pad($line, count($header), null);
            $rows[] = array_map(fn ($v) => $v === '' ? null : $v, array_combine($header, array_slice($line, 0, count($header))));
        }
        fclose($handle);

        return $rows;
    }
}
