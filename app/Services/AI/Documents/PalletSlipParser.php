<?php

namespace App\Services\AI\Documents;

use App\AI\Prompts\PromptLibrary;
use App\AI\Services\AiGateway;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Component\Process\Process;

/**
 * Parses packing slips, purchase orders, and manifests into structured lines.
 *
 * Text-based PDFs first use pdftotext + JSON normalization because supplier PDFs
 * usually contain selectable text and this is much more reliable than asking a
 * vision model to OCR a rendered page. Scanned PDFs/images still fall back to
 * the vision model. CSV/XLS/XLSX use deterministic header mapping first.
 */
class PalletSlipParser
{
    public function __construct(
        private readonly AiGateway $gateway,
        private readonly PromptLibrary $prompts,
    ) {}

    /** @return list<array<string,mixed>> */
    public function parse(string $storedPath): array
    {
        try {
            $ext = strtolower(pathinfo($storedPath, PATHINFO_EXTENSION));

            if (in_array($ext, ['csv', 'xls', 'xlsx'], true)) {
                return $this->parseSpreadsheet($storedPath);
            }

            if ($ext === 'txt') {
                return $this->normalizeUnknownRows(
                    array_values(array_filter(array_map('trim', file($storedPath, FILE_IGNORE_NEW_LINES) ?: [])))
                );
            }

            if ($ext === 'pdf') {
                $textRows = $this->pdfTextRows($storedPath);

                if ($textRows !== []) {
                    try {
                        $textLines = $this->normalizeUnknownRows($textRows);
                        if ($textLines !== []) {
                            Log::info('PalletSlipParser extracted PDF lines from text layer', [
                                'lines' => count($textLines),
                            ]);
                            return $textLines;
                        }

                        Log::warning('PalletSlipParser PDF text layer produced zero normalized lines; falling back to vision');
                    } catch (\Throwable $textError) {
                        Log::warning('PalletSlipParser PDF text normalization failed; falling back to vision', [
                            'error' => $textError->getMessage(),
                        ]);
                    }
                }
            }

            $images = $this->toBase64Images($storedPath);
            $lines = [];

            foreach ($images as $b64) {
                $lines = array_merge($lines, $this->extractFromImage($b64));
            }

            if ($lines === []) {
                throw new \RuntimeException(
                    'AI could not extract any manifest line items from this file. The job was not marked complete so you can retry or use a different document.'
                );
            }

            return $lines;
        } finally {
            @unlink($storedPath);
        }
    }

    /** @return list<array<string,mixed>> */
    private function parseSpreadsheet(string $path): array
    {
        if (! file_exists($path)) {
            throw new \RuntimeException("Uploaded manifest not found: {$path}");
        }

        $sheet = IOFactory::load($path)->getActiveSheet();
        $rows = array_values(array_filter(
            $sheet->toArray(null, true, true, false),
            fn (array $row) => collect($row)->contains(fn ($value) => filled($value))
        ));

        if ($rows === []) {
            return [];
        }

        $headerIndex = null;
        $columnMap = [];
        foreach (array_slice($rows, 0, 12, true) as $index => $row) {
            $map = $this->mapHeaders($row);
            if (isset($map['description']) && count($map) >= 2) {
                $headerIndex = $index;
                $columnMap = $map;
                break;
            }
        }

        if ($headerIndex !== null) {
            $out = [];
            foreach (array_slice($rows, $headerIndex + 1) as $row) {
                $description = trim((string) ($row[$columnMap['description']] ?? ''));
                if ($description === '') continue;

                $qty = isset($columnMap['case_count']) ? $this->number($row[$columnMap['case_count']] ?? 1) : 1;
                $unitsPerCase = isset($columnMap['quantity_per_case']) ? $this->number($row[$columnMap['quantity_per_case']] ?? 1) : 1;
                $cost = isset($columnMap['unit_cost']) ? $this->money($row[$columnMap['unit_cost']] ?? null) : null;

                $out[] = [
                    'description' => $description,
                    'case_count' => max(1, (int) round($qty ?: 1)),
                    'quantity_per_case' => max(1, (int) round($unitsPerCase ?: 1)),
                    'unit_cost' => $cost,
                    'sku' => isset($columnMap['sku']) ? $this->cleanIdentifier($row[$columnMap['sku']] ?? null) : null,
                    'barcode' => isset($columnMap['barcode']) ? $this->cleanIdentifier($row[$columnMap['barcode']] ?? null) : null,
                ];
            }

            if ($out !== []) return $out;
        }

        $bounded = array_slice($rows, 0, 120);
        return $this->normalizeUnknownRows(array_map(
            fn (array $row) => implode(' | ', array_map(fn ($value) => trim((string) $value), $row)),
            $bounded
        ));
    }

    /** @return array<string,int> */
    private function mapHeaders(array $row): array
    {
        $aliases = [
            'description' => ['description', 'item description', 'product description', 'product', 'item', 'name', 'product name', 'title', 'vendor description'],
            'case_count' => ['qty', 'quantity', 'case count', 'cases', 'case qty', 'ordered', 'order qty'],
            'quantity_per_case' => ['units per case', 'units/case', 'units / case', 'pack', 'pack qty', 'case pack'],
            'unit_cost' => ['unit cost', 'cost', 'unit price', 'price', 'wholesale', 'cost each', 'each cost'],
            'sku' => ['sku', 'vendor sku', 'item sku', 'item number', 'item #', 'product code', 'part number', 'part #'],
            'barcode' => ['barcode', 'upc', 'upc code', 'ean', 'gtin', 'upc / barcode'],
        ];

        $normalizedAliases = collect($aliases)->map(fn ($values) => array_map([$this, 'normalizeHeader'], $values));
        $map = [];

        foreach ($row as $index => $value) {
            $header = $this->normalizeHeader((string) $value);
            if ($header === '') continue;

            foreach ($normalizedAliases as $field => $values) {
                if (! isset($map[$field]) && in_array($header, $values, true)) {
                    $map[$field] = $index;
                    break;
                }
            }
        }

        return $map;
    }

    private function normalizeHeader(string $value): string
    {
        return Str::of($value)->lower()->replace(['_', '-'], ' ')->squish()->toString();
    }

    private function number(mixed $value): float
    {
        $clean = preg_replace('/[^0-9.\-]/', '', (string) $value) ?: '';
        return is_numeric($clean) ? (float) $clean : 0.0;
    }

    private function money(mixed $value): ?float
    {
        if ($value === null || trim((string) $value) === '') return null;
        $number = $this->number($value);
        return $number >= 0 ? $number : null;
    }

    private function cleanIdentifier(mixed $value): ?string
    {
        if ($value === null) return null;
        $clean = trim((string) $value);
        return $clean !== '' ? $clean : null;
    }

    /** @param list<string> $rows @return list<array<string,mixed>> */
    private function normalizeUnknownRows(array $rows): array
    {
        $text = Str::limit(implode("\n", $rows), 18000, '');
        if ($text === '') return [];

        $result = $this->gateway->json([
            [
                'role' => 'system',
                'content' => 'Extract EVERY purchase-order, packing-slip, or receiving-manifest product line from the supplied document text. Return JSON only as {"lines":[...]}. Each line must use: description, case_count, quantity_per_case, unit_cost, sku, barcode. Preserve the supplier product description. case_count is the number of cases/cartons; quantity_per_case is units in each case. If the document shows a single quantity but not a case structure, use case_count 1 and quantity_per_case equal to that quantity. Do not include headers, addresses, shipping, fees, totals, notes, terms, or metadata. Do not invent missing identifiers; use null.',
            ],
            ['role' => 'user', 'content' => $text],
        ], [
            'temperature' => 0.0,
            'max_tokens' => 3200,
            'context_length' => 8192,
        ]);

        if (! is_array($result)) {
            throw new \RuntimeException('Manifest text normalization did not return structured JSON.');
        }

        $lines = $this->normalizeLines($result['lines'] ?? $result);
        Log::info('PalletSlipParser normalized document text', [
            'input_chars' => strlen($text),
            'lines' => count($lines),
        ]);

        return $lines;
    }

    /** @return list<string> */
    private function pdfTextRows(string $pdfPath): array
    {
        if (! file_exists($pdfPath)) {
            throw new \RuntimeException("Uploaded PDF not found: {$pdfPath}");
        }

        $binary = collect(['/usr/bin/pdftotext', '/usr/local/bin/pdftotext'])
            ->first(fn (string $candidate) => is_file($candidate) && is_executable($candidate));

        if (! $binary) {
            Log::notice('PalletSlipParser pdftotext not available; using vision fallback');
            return [];
        }

        $process = new Process([$binary, '-layout', '-nopgbrk', $pdfPath, '-']);
        $process->setTimeout(60);
        $process->run();

        if (! $process->isSuccessful()) {
            Log::warning('PalletSlipParser pdftotext failed; using vision fallback', [
                'exit_code' => $process->getExitCode(),
                'error' => Str::limit(trim($process->getErrorOutput()), 300),
            ]);
            return [];
        }

        $text = trim($process->getOutput());
        if ($text === '') return [];

        $rows = preg_split('/\R/u', $text) ?: [];
        $rows = array_values(array_filter(array_map(
            fn ($row) => trim((string) $row),
            $rows
        ), fn ($row) => $row !== ''));

        Log::info('PalletSlipParser extracted PDF text layer', [
            'characters' => strlen($text),
            'rows' => count($rows),
        ]);

        return $rows;
    }

    /** @return list<string> */
    private function toBase64Images(string $path): array
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if ($ext === 'pdf') return $this->pdfToImages($path);

        if (! file_exists($path)) {
            throw new \RuntimeException("Uploaded file not found: {$path}");
        }

        return [base64_encode(file_get_contents($path))];
    }

    /** @return list<string> */
    private function pdfToImages(string $pdfPath): array
    {
        if (extension_loaded('imagick')) {
            $im = new \Imagick();
            $im->setResolution(150, 150);
            $im->readImage("{$pdfPath}[0-9]");
            $images = [];

            foreach ($im as $page) {
                $page->setImageFormat('png');
                $page->setImageAlphaChannel(\Imagick::ALPHACHANNEL_REMOVE);
                $page->mergeImageLayers(\Imagick::LAYERMETHOD_FLATTEN);
                $images[] = base64_encode($page->getImageBlob());
            }

            $im->clear();
            if ($images !== []) return $images;
        }

        if (! file_exists($pdfPath)) {
            throw new \RuntimeException("Uploaded PDF not found: {$pdfPath}");
        }

        $binary = collect(['/usr/bin/pdftoppm', '/usr/local/bin/pdftoppm'])
            ->first(fn (string $candidate) => is_file($candidate) && is_executable($candidate));

        if (! $binary) {
            throw new \RuntimeException('PDF conversion cannot find executable pdftoppm. Install poppler-utils or configure the worker environment.');
        }

        $prefix = sys_get_temp_dir() . '/slip_' . uniqid();
        $process = new Process([
            $binary, '-r', '150', '-png', '-f', '1', '-l', '1', $pdfPath, $prefix,
        ]);
        $process->setTimeout(120);
        $process->run();

        $generated = $prefix . '-1.png';
        if ($process->isSuccessful() && file_exists($generated) && filesize($generated) > 0) {
            $b64 = base64_encode(file_get_contents($generated));
            @unlink($generated);
            return [$b64];
        }

        @unlink($generated);
        $detail = trim($process->getErrorOutput() ?: $process->getOutput());
        Log::error('Pallet PDF conversion failed', [
            'binary' => $binary,
            'exit_code' => $process->getExitCode(),
            'error' => $detail,
            'pdf_exists' => file_exists($pdfPath),
            'pdf_readable' => is_readable($pdfPath),
            'temp_dir' => sys_get_temp_dir(),
        ]);

        throw new \RuntimeException(
            'PDF conversion failed with pdftoppm' . ($detail !== '' ? ': ' . Str::limit($detail, 180) : '.')
        );
    }

    /** @return list<array<string,mixed>> */
    private function extractFromImage(string $base64Image): array
    {
        $response = $this->gateway->vision($this->prompts->slipExtraction(), $base64Image);
        if (! $response->success) {
            throw new \RuntimeException("Vision extraction failed: {$response->error}");
        }

        $lines = $this->parseJsonLines($response->content);
        Log::info('PalletSlipParser vision extraction completed', [
            'response_chars' => strlen($response->content),
            'lines' => count($lines),
        ]);
        return $lines;
    }

    /** @return list<array<string,mixed>> */
    private function parseJsonLines(string $text): array
    {
        $direct = json_decode($text, true);
        if (isset($direct['lines']) && is_array($direct['lines'])) {
            return $this->normalizeLines($direct['lines']);
        }

        if (preg_match('/\{.*"lines"\s*:\s*\[.*?\]\s*\}/s', $text, $m)) {
            $parsed = json_decode($m[0], true);
            if (isset($parsed['lines']) && is_array($parsed['lines'])) {
                return $this->normalizeLines($parsed['lines']);
            }
        }

        if (preg_match('/\[.*\]/s', $text, $m)) {
            $parsed = json_decode($m[0], true);
            if (is_array($parsed)) return $this->normalizeLines($parsed);
        }

        Log::warning('PalletSlipParser: could not extract JSON from vision response', [
            'sample' => substr($text, 0, 700),
        ]);
        return [];
    }

    /** @return list<array<string,mixed>> */
    private function normalizeLines(array $raw): array
    {
        $out = [];
        foreach ($raw as $item) {
            if (! is_array($item)) continue;

            $description = trim((string) ($item['description'] ?? $item['name'] ?? $item['item'] ?? ''));
            if ($description === '') continue;

            $caseCount = max(1, (int) ($item['case_count'] ?? $item['cases'] ?? 1));
            $quantityPerCase = max(1, (int) ($item['quantity_per_case'] ?? $item['units_per_case'] ?? $item['pack_qty'] ?? 1));

            // Older prompts/providers may return only qty/quantity. Preserve the
            // quantity without pretending it represents multiple cases.
            if (! isset($item['case_count']) && ! isset($item['quantity_per_case'])) {
                $quantityPerCase = max(1, (int) ($item['qty'] ?? $item['quantity'] ?? 1));
                $caseCount = 1;
            }

            $out[] = [
                'description' => $description,
                'case_count' => $caseCount,
                'quantity_per_case' => $quantityPerCase,
                'unit_cost' => isset($item['unit_cost']) && $item['unit_cost'] !== null ? (float) $item['unit_cost'] : null,
                'sku' => ($item['sku'] ?? null) ? trim((string) $item['sku']) : null,
                'barcode' => ($item['barcode'] ?? $item['upc'] ?? null) ? trim((string) ($item['barcode'] ?? $item['upc'])) : null,
            ];
        }

        return $out;
    }
}
