<?php

namespace App\Services\AI\Documents;

use App\AI\Prompts\PromptLibrary;
use App\AI\Services\AiGateway;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Component\Process\Process;

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
                return $this->normalizeUnknownRows(array_values(array_filter(array_map(
                    'trim',
                    file($storedPath, FILE_IGNORE_NEW_LINES) ?: []
                ))));
            }

            if ($ext === 'pdf') {
                $textRows = $this->pdfTextRows($storedPath);

                if ($textRows !== []) {
                    $tableLines = $this->parseTextTableRows($textRows);
                    if ($tableLines !== []) {
                        Log::info('PalletSlipParser parsed PDF table deterministically', [
                            'lines' => count($tableLines),
                        ]);
                        return $tableLines;
                    }

                    try {
                        Log::info('PalletSlipParser starting AI text normalization', ['rows' => count($textRows)]);
                        $textLines = $this->normalizeUnknownRows($textRows);
                        if ($textLines !== []) {
                            Log::info('PalletSlipParser extracted PDF lines from text layer', ['lines' => count($textLines)]);
                            return $textLines;
                        }
                    } catch (\Throwable $textError) {
                        Log::warning('PalletSlipParser PDF text normalization failed; falling back to vision', [
                            'error' => $textError->getMessage(),
                        ]);
                    }
                }
            }

            $lines = [];
            foreach ($this->toBase64Images($storedPath) as $b64) {
                $lines = array_merge($lines, $this->extractFromImage($b64));
            }

            if ($lines === []) {
                throw new \RuntimeException('AI could not extract any manifest line items from this file.');
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

        $rows = array_values(array_filter(
            IOFactory::load($path)->getActiveSheet()->toArray(null, true, true, false),
            fn (array $row) => collect($row)->contains(fn ($value) => filled($value))
        ));

        if ($rows === []) return [];

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

                $out[] = [
                    'description' => $description,
                    'case_count' => max(1, (int) round($qty ?: 1)),
                    'quantity_per_case' => max(1, (int) round($unitsPerCase ?: 1)),
                    'unit_cost' => isset($columnMap['unit_cost']) ? $this->money($row[$columnMap['unit_cost']] ?? null) : null,
                    'sku' => isset($columnMap['sku']) ? $this->cleanIdentifier($row[$columnMap['sku']] ?? null) : null,
                    'barcode' => isset($columnMap['barcode']) ? $this->cleanIdentifier($row[$columnMap['barcode']] ?? null) : null,
                ];
            }
            if ($out !== []) return $out;
        }

        return $this->normalizeUnknownRows(array_map(
            fn (array $row) => implode(' | ', array_map(fn ($value) => trim((string) $value), $row)),
            array_slice($rows, 0, 120)
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
        $clean = preg_replace('/[^0-9.\-]/', '', (string) $value) ?: '';
        return is_numeric($clean) ? (float) $clean : null;
    }

    private function cleanIdentifier(mixed $value): ?string
    {
        if ($value === null) return null;
        $clean = trim((string) $value);
        return $clean !== '' ? $clean : null;
    }

    /**
     * Build logical line-item blocks first. pdftotext may wrap one item across
     * multiple physical rows (for example the UPC may land on the next row), so
     * treating each physical row as a complete item loses valid products.
     *
     * @param list<string> $rows
     * @return list<array<string,mixed>>
     */
    private function parseTextTableRows(array $rows): array
    {
        $blocks = [];
        $current = null;

        foreach ($rows as $row) {
            $row = trim((string) $row);
            if ($row === '') continue;

            if (preg_match('/^(\d{1,4})\s+(.+)$/u', $row, $start)) {
                if ($current !== null) $blocks[] = $current;
                $current = ['line' => (int) $start[1], 'text' => trim($start[2])];
                continue;
            }

            if ($current !== null) {
                // Stop carrying a completed line into document totals/notes.
                if (preg_match('/^(AI MATCHING|SUBTOTAL|MERCHANDISE|SHIPPING|PAYMENT|TOTAL|NOTES?|TERMS?)\b/i', $row)) {
                    $blocks[] = $current;
                    $current = null;
                    continue;
                }
                $current['text'] .= ' ' . $row;
            }
        }
        if ($current !== null) $blocks[] = $current;

        $out = [];
        $rejected = [];

        foreach ($blocks as $block) {
            $text = preg_replace('/\s+/u', ' ', trim($block['text'])) ?: '';

            // Find the stable tail first: UPC + cases + units/case + cost + total.
            if (! preg_match(
                '/^(.*?)(\d{8,14})\s+(\d+(?:\.\d+)?)\s+(\d+(?:\.\d+)?)\s+\$?([\d,]+(?:\.\d{1,2})?)\s+\$?([\d,]+(?:\.\d{1,2})?)(?:\s+.*)?$/u',
                $text,
                $m
            )) {
                $rejected[] = ['line' => $block['line'], 'reason' => 'tail_not_recognized', 'sample' => Str::limit($text, 180)];
                continue;
            }

            $prefix = trim($m[1]);
            $barcode = $m[2];
            $cases = $this->number($m[3]);
            $unitsPerCase = $this->number($m[4]);
            $unitCost = $this->money($m[5]);

            // SKU is the final code-like token before UPC. It may be glued to the
            // description by pdftotext (e.g. "VersionVND-151-GATH-CH"). Prefer
            // an explicit separated token, then fall back to a trailing code.
            $sku = null;
            $description = $prefix;
            if (preg_match('/^(.*?)\s+([A-Z0-9][A-Z0-9._\/-]*[-_][A-Z0-9._\/-]+)$/u', $prefix, $skuMatch)) {
                $description = trim($skuMatch[1]);
                $sku = trim($skuMatch[2]);
            } elseif (preg_match('/^(.*?)([A-Z]{2,}[A-Z0-9._\/-]*[-_][A-Z0-9._\/-]+)$/u', $prefix, $skuMatch)) {
                $description = trim($skuMatch[1]);
                $sku = trim($skuMatch[2]);
            }

            if ($description === '' || $cases <= 0 || $unitsPerCase <= 0 || $unitCost === null) {
                $rejected[] = ['line' => $block['line'], 'reason' => 'invalid_fields', 'sample' => Str::limit($text, 180)];
                continue;
            }

            $out[] = [
                'description' => $description,
                'case_count' => max(1, (int) round($cases)),
                'quantity_per_case' => max(1, (int) round($unitsPerCase)),
                'unit_cost' => $unitCost,
                'sku' => $sku,
                'barcode' => $barcode,
            ];
        }

        Log::info('PalletSlipParser deterministic table diagnostics', [
            'candidate_blocks' => count($blocks),
            'accepted' => count($out),
            'rejected' => $rejected,
        ]);

        return $out;
    }

    /** @param list<string> $rows @return list<array<string,mixed>> */
    private function normalizeUnknownRows(array $rows): array
    {
        $text = Str::limit(implode("\n", $rows), 18000, '');
        if ($text === '') return [];

        Log::info('PalletSlipParser sending document text to AI normalization', [
            'input_chars' => strlen($text),
            'rows' => count($rows),
        ]);

        $result = $this->gateway->json([
            [
                'role' => 'system',
                'content' => 'Extract EVERY purchase-order, packing-slip, or receiving-manifest product line from the supplied document text. Return JSON only as {"lines":[...]}. Each line must use: description, case_count, quantity_per_case, unit_cost, sku, barcode. Preserve the supplier product description. Do not include headers, addresses, shipping, fees, totals, notes, terms, or metadata. Do not invent missing identifiers; use null.',
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
        Log::info('PalletSlipParser normalized document text', ['input_chars' => strlen($text), 'lines' => count($lines)]);
        return $lines;
    }

    /** @return list<string> */
    private function pdfTextRows(string $pdfPath): array
    {
        if (! file_exists($pdfPath)) throw new \RuntimeException("Uploaded PDF not found: {$pdfPath}");

        $binary = collect(['/usr/bin/pdftotext', '/usr/local/bin/pdftotext'])
            ->first(fn (string $candidate) => is_file($candidate) && is_executable($candidate));
        if (! $binary) return [];

        $process = new Process([$binary, '-layout', '-nopgbrk', $pdfPath, '-']);
        $process->setTimeout(60);
        $process->run();
        if (! $process->isSuccessful()) return [];

        $text = trim($process->getOutput());
        if ($text === '') return [];
        $rows = preg_split('/\R/u', $text) ?: [];
        $rows = array_values(array_filter(array_map(fn ($row) => trim((string) $row), $rows), fn ($row) => $row !== ''));

        Log::info('PalletSlipParser extracted PDF text layer', ['characters' => strlen($text), 'rows' => count($rows)]);
        return $rows;
    }

    /** @return list<string> */
    private function toBase64Images(string $path): array
    {
        if (strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'pdf') return $this->pdfToImages($path);
        if (! file_exists($path)) throw new \RuntimeException("Uploaded file not found: {$path}");
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

        $binary = collect(['/usr/bin/pdftoppm', '/usr/local/bin/pdftoppm'])
            ->first(fn (string $candidate) => is_file($candidate) && is_executable($candidate));
        if (! $binary) throw new \RuntimeException('PDF conversion cannot find executable pdftoppm.');

        $prefix = sys_get_temp_dir() . '/slip_' . uniqid();
        $process = new Process([$binary, '-r', '150', '-png', '-f', '1', '-l', '1', $pdfPath, $prefix]);
        $process->setTimeout(120);
        $process->run();
        $generated = $prefix . '-1.png';

        if ($process->isSuccessful() && file_exists($generated) && filesize($generated) > 0) {
            $b64 = base64_encode(file_get_contents($generated));
            @unlink($generated);
            return [$b64];
        }

        @unlink($generated);
        throw new \RuntimeException('PDF conversion failed with pdftoppm: ' . Str::limit(trim($process->getErrorOutput()), 180));
    }

    /** @return list<array<string,mixed>> */
    private function extractFromImage(string $base64Image): array
    {
        $response = $this->gateway->vision($this->prompts->slipExtraction(), $base64Image);
        if (! $response->success) throw new \RuntimeException("Vision extraction failed: {$response->error}");
        return $this->parseJsonLines($response->content);
    }

    /** @return list<array<string,mixed>> */
    private function parseJsonLines(string $text): array
    {
        $direct = json_decode($text, true);
        if (isset($direct['lines']) && is_array($direct['lines'])) return $this->normalizeLines($direct['lines']);
        if (preg_match('/\{.*"lines"\s*:\s*\[.*?\]\s*\}/s', $text, $m)) {
            $parsed = json_decode($m[0], true);
            if (isset($parsed['lines']) && is_array($parsed['lines'])) return $this->normalizeLines($parsed['lines']);
        }
        if (preg_match('/\[.*\]/s', $text, $m)) {
            $parsed = json_decode($m[0], true);
            if (is_array($parsed)) return $this->normalizeLines($parsed);
        }
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
