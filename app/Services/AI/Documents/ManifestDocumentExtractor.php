<?php

namespace App\Services\AI\Documents;

use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Component\Process\Process;
use ZipArchive;

final class ManifestDocumentExtractor
{
    public function extract(string $path): DocumentExtractionResult
    {
        if (! is_file($path)) {
            throw new \RuntimeException("Manifest file not found: {$path}");
        }

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return match ($ext) {
            'pdf' => $this->extractPdf($path),
            'csv', 'xls', 'xlsx' => $this->extractSpreadsheet($path, $ext),
            'txt' => $this->extractText($path, $ext),
            'docx' => $this->extractDocx($path),
            'doc' => $this->extractLegacyDoc($path),
            'jpg', 'jpeg', 'png', 'gif', 'webp' => new DocumentExtractionResult($ext, 'image', requiresVision: true),
            default => throw new \RuntimeException("Unsupported manifest file type: {$ext}"),
        };
    }

    private function extractPdf(string $path): DocumentExtractionResult
    {
        $binary = collect(['/usr/bin/pdftotext', '/usr/local/bin/pdftotext'])
            ->first(fn (string $candidate) => is_file($candidate) && is_executable($candidate));

        if (! $binary) {
            Log::warning('ManifestDocumentExtractor pdftotext unavailable; using vision fallback');
            return new DocumentExtractionResult('pdf', 'pdf', requiresVision: true, metadata: ['text_extractor' => 'missing']);
        }

        $process = new Process([$binary, '-layout', '-nopgbrk', $path, '-']);
        $process->setTimeout(60);
        $process->run();

        if (! $process->isSuccessful()) {
            Log::warning('ManifestDocumentExtractor pdftotext failed; using vision fallback', [
                'error' => trim($process->getErrorOutput()),
            ]);
            return new DocumentExtractionResult('pdf', 'pdf', requiresVision: true, metadata: ['text_extractor' => 'failed']);
        }

        $text = trim($process->getOutput());
        $rows = $this->cleanTextRows($text);
        $usableChars = strlen(preg_replace('/\s+/u', '', $text) ?: '');
        $requiresVision = $usableChars < 80 || count($rows) < 2;

        Log::info('ManifestDocumentExtractor PDF extraction complete', [
            'characters' => strlen($text),
            'usable_characters' => $usableChars,
            'rows' => count($rows),
            'requires_vision' => $requiresVision,
        ]);

        return new DocumentExtractionResult(
            extension: 'pdf',
            kind: 'pdf',
            textRows: $rows,
            requiresVision: $requiresVision,
            metadata: ['text_extractor' => 'pdftotext'],
        );
    }

    private function extractSpreadsheet(string $path, string $ext): DocumentExtractionResult
    {
        $spreadsheet = IOFactory::load($path);
        $tableRows = [];
        $textRows = [];
        $sheetCount = 0;

        foreach ($spreadsheet->getWorksheetIterator() as $sheet) {
            $sheetCount++;
            if ($sheetCount > 8) break;

            $textRows[] = 'SHEET: ' . $sheet->getTitle();
            $rows = $sheet->toArray(null, true, true, false);
            $kept = 0;
            foreach ($rows as $row) {
                $row = array_map(fn ($value) => is_scalar($value) ? trim((string) $value) : '', $row);
                if (! collect($row)->contains(fn ($value) => $value !== '')) continue;
                $tableRows[] = $row;
                $textRows[] = implode(' | ', $row);
                $kept++;
                if ($kept >= 500) break;
            }
        }

        Log::info('ManifestDocumentExtractor spreadsheet extraction complete', [
            'sheets' => $sheetCount,
            'rows' => count($tableRows),
        ]);

        return new DocumentExtractionResult($ext, 'spreadsheet', $textRows, $tableRows, false, ['sheets' => $sheetCount]);
    }

    private function extractText(string $path, string $ext): DocumentExtractionResult
    {
        $text = (string) file_get_contents($path);
        return new DocumentExtractionResult($ext, 'text', $this->cleanTextRows($text));
    }

    private function extractDocx(string $path): DocumentExtractionResult
    {
        if (! class_exists(ZipArchive::class)) {
            throw new \RuntimeException('DOCX extraction requires the PHP zip extension.');
        }

        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new \RuntimeException('Could not open DOCX manifest.');
        }

        try {
            $xml = $zip->getFromName('word/document.xml');
            if (! is_string($xml) || $xml === '') {
                throw new \RuntimeException('DOCX does not contain word/document.xml.');
            }

            $dom = new \DOMDocument();
            $previous = libxml_use_internal_errors(true);
            $loaded = $dom->loadXML($xml);
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
            if (! $loaded) {
                throw new \RuntimeException('Could not parse DOCX document XML.');
            }

            $xpath = new \DOMXPath($dom);
            $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

            $rows = [];
            foreach ($xpath->query('//w:body/w:p | //w:body/w:tbl/w:tr') ?: [] as $node) {
                if ($node->localName === 'tr') {
                    $cells = [];
                    foreach ($xpath->query('.//w:tc', $node) ?: [] as $cell) {
                        $parts = [];
                        foreach ($xpath->query('.//w:t', $cell) ?: [] as $textNode) $parts[] = $textNode->textContent;
                        $cellText = trim(implode(' ', $parts));
                        if ($cellText !== '') $cells[] = $cellText;
                    }
                    if ($cells !== []) $rows[] = implode(' | ', $cells);
                    continue;
                }

                $parts = [];
                foreach ($xpath->query('.//w:t', $node) ?: [] as $textNode) $parts[] = $textNode->textContent;
                $line = trim(implode(' ', $parts));
                if ($line !== '') $rows[] = $line;
            }

            Log::info('ManifestDocumentExtractor DOCX extraction complete', ['rows' => count($rows)]);
            return new DocumentExtractionResult('docx', 'document', array_values(array_unique($rows)));
        } finally {
            $zip->close();
        }
    }

    private function extractLegacyDoc(string $path): DocumentExtractionResult
    {
        $binary = collect(['/usr/bin/antiword', '/usr/local/bin/antiword', '/usr/bin/catdoc', '/usr/local/bin/catdoc'])
            ->first(fn (string $candidate) => is_file($candidate) && is_executable($candidate));

        if (! $binary) {
            throw new \RuntimeException('Legacy .doc extraction needs antiword or catdoc installed. Save the file as DOCX or PDF and retry.');
        }

        $process = new Process([$binary, $path]);
        $process->setTimeout(60);
        $process->run();
        if (! $process->isSuccessful()) {
            throw new \RuntimeException('Could not extract text from legacy DOC manifest.');
        }

        return new DocumentExtractionResult('doc', 'document', $this->cleanTextRows($process->getOutput()));
    }

    /** @return list<string> */
    private function cleanTextRows(string $text): array
    {
        $rows = preg_split('/\R/u', $text) ?: [];
        return array_values(array_filter(array_map(
            fn ($row) => trim(preg_replace('/[\t ]+/u', ' ', (string) $row) ?: ''),
            $rows
        ), fn ($row) => $row !== ''));
    }
}
