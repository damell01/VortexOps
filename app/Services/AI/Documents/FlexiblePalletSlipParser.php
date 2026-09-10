<?php

namespace App\Services\AI\Documents;

use App\AI\Prompts\PromptLibrary;
use App\AI\Services\AiGateway;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

final class FlexiblePalletSlipParser
{
    public function __construct(
        private readonly ManifestDocumentExtractor $extractor,
        private readonly AiGateway $gateway,
        private readonly PromptLibrary $prompts,
    ) {}

    /** @return list<array<string,mixed>> */
    public function parse(string $storedPath): array
    {
        try {
            $document = $this->extractor->extract($storedPath);

            Log::info('FlexiblePalletSlipParser document extracted', [
                'extension' => $document->extension,
                'kind' => $document->kind,
                'text_rows' => count($document->textRows),
                'table_rows' => count($document->tableRows),
                'requires_vision' => $document->requiresVision,
            ]);

            if ($document->hasTableRows()) {
                $mapped = $this->parseKnownSpreadsheetHeaders($document->tableRows);
                if ($mapped !== []) {
                    Log::info('FlexiblePalletSlipParser used spreadsheet header mapping', ['lines' => count($mapped)]);
                    return $mapped;
                }
            }

            if ($document->hasText() && ! $document->requiresVision) {
                $rows = $this->prepareTextRowsForAi($document->textRows);
                $normalized = $this->normalizeExtractedText($rows, $document);
                if ($normalized !== []) {
                    Log::info('FlexiblePalletSlipParser normalized extracted text', [
                        'lines' => count($normalized),
                        'kind' => $document->kind,
                    ]);
                    return $normalized;
                }
            }

            if ($document->requiresVision || ! $document->hasText()) {
                $vision = $this->extractWithVision($storedPath, $document->extension);
                if ($vision !== []) return $vision;
            }

            throw new \RuntimeException('Manifest extraction completed, but no usable product lines were found.');
        } finally {
            @unlink($storedPath);
        }
    }

    /**
     * Keep deterministic spreadsheet parsing only when the column names make the
     * meaning obvious. Unknown spreadsheets fall through to the AI normalizer.
     *
     * @param list<list<mixed>> $rows
     * @return list<array<string,mixed>>
     */
    private function parseKnownSpreadsheetHeaders(array $rows): array
    {
        $aliases = [
            'description' => ['description','item description','product description','product','item','name','product name','title','vendor description'],
            'case_count' => ['qty','quantity','case count','cases','case qty','ordered','order qty'],
            'quantity_per_case' => ['units per case','units/case','units / case','pack','pack qty','case pack'],
            'unit_cost' => ['unit cost','cost','unit price','price','wholesale','cost each','each cost'],
            'sku' => ['sku','vendor sku','item sku','item number','item #','product code','part number','part #'],
            'barcode' => ['barcode','upc','upc code','ean','gtin','upc / barcode'],
        ];

        foreach (array_slice($rows, 0, 15, true) as $headerIndex => $row) {
            $map = [];
            foreach ($row as $index => $value) {
                $header = $this->normalizeHeader((string) $value);
                foreach ($aliases as $field => $names) {
                    if (! isset($map[$field]) && in_array($header, array_map([$this, 'normalizeHeader'], $names), true)) {
                        $map[$field] = $index;
                    }
                }
            }

            if (! isset($map['description']) || count($map) < 2) continue;

            $out = [];
            foreach (array_slice($rows, $headerIndex + 1) as $dataRow) {
                $description = trim((string) ($dataRow[$map['description']] ?? ''));
                if ($description === '') continue;

                $caseCount = isset($map['case_count']) ? max(1, (int) round($this->number($dataRow[$map['case_count']] ?? 1))) : 1;
                $unitsPerCase = isset($map['quantity_per_case']) ? max(1, (int) round($this->number($dataRow[$map['quantity_per_case']] ?? 1))) : 1;
                $unitCost = isset($map['unit_cost']) ? $this->money($dataRow[$map['unit_cost']] ?? null) : null;

                $out[] = [
                    'description' => $description,
                    'case_count' => $caseCount,
                    'quantity_per_case' => $unitsPerCase,
                    'unit_cost' => $unitCost,
                    'sku' => isset($map['sku']) ? $this->identifier($dataRow[$map['sku']] ?? null) : null,
                    'barcode' => isset($map['barcode']) ? $this->identifier($dataRow[$map['barcode']] ?? null) : null,
                ];
            }

            if ($out !== []) return $out;
        }

        return [];
    }

    /** @param list<string> $rows @return list<string> */
    private function prepareTextRowsForAi(array $rows): array
    {
        $rows = array_values(array_filter(array_map(function ($row) {
            $row = trim(preg_replace('/\s+/u', ' ', (string) $row) ?: '');
            if ($row === '') return null;
            return $row;
        }, $rows)));

        // Keep enough context to understand headers and wrapped descriptions, but
        // avoid huge prompts from terms pages or repeated footer text.
        return array_slice($rows, 0, 260);
    }

    /** @param list<string> $rows @return list<array<string,mixed>> */
    private function normalizeExtractedText(array $rows, DocumentExtractionResult $document): array
    {
        $text = Str::limit(implode("\n", $rows), 22000, '');
        if ($text === '') return [];

        $system = <<<'PROMPT'
You are normalizing product line items from a purchase order, invoice, packing slip, receiving manifest, spreadsheet, or document whose raw text has already been extracted by software.

The layout is UNKNOWN and may vary completely between vendors. Infer columns and wrapped rows from context rather than expecting one template.

Return JSON only in this exact shape:
{"lines":[{"description":"...","case_count":1,"quantity_per_case":1,"unit_cost":12.34,"sku":null,"barcode":null}]}

Rules:
- Extract merchandise/product rows only. Exclude addresses, order metadata, taxes, shipping, discounts, fees, subtotals, totals, notes and terms.
- description is required and should preserve the supplier's product name.
- sku is a vendor SKU/item/part/product code when explicitly present; otherwise null.
- barcode is UPC/EAN/GTIN when explicitly present; otherwise null. Never invent one.
- case_count means number of cases/boxes/cartons when the document explicitly provides case packaging. If the document only provides a normal item quantity, use case_count=quantity and quantity_per_case=1.
- quantity_per_case means units inside each case/box only when the document makes that relationship clear; otherwise 1.
- unit_cost is the per-case price when case_count is cases; otherwise the per-item/unit price. Use null if price is not shown.
- Use extended totals only to validate quantity and unit cost; do not put extended total into unit_cost.
- Merge wrapped physical text lines that belong to the same product.
- Do not assume every supplier uses the same ordering, headers, SKU format, or money format.
- Include every distinct merchandise row you can support from the supplied text.
PROMPT;

        Log::info('FlexiblePalletSlipParser sending extracted content to AI', [
            'kind' => $document->kind,
            'characters' => strlen($text),
            'rows' => count($rows),
        ]);

        $result = $this->gateway->json([
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => "SOURCE TYPE: {$document->kind}\n\nEXTRACTED CONTENT:\n{$text}"],
        ], [
            'temperature' => 0.0,
            'max_tokens' => 4000,
            'context_length' => 12288,
            'timeout' => 120,
        ]);

        if (! is_array($result)) return [];
        return $this->normalizeLines($result['lines'] ?? $result);
    }

    /** @return list<array<string,mixed>> */
    private function extractWithVision(string $path, string $extension): array
    {
        $images = $extension === 'pdf' ? $this->pdfToImages($path) : [base64_encode((string) file_get_contents($path))];
        $lines = [];

        foreach ($images as $index => $image) {
            $response = $this->gateway->vision($this->prompts->slipExtraction(), $image, ['timeout' => 180]);
            if (! $response->success) {
                Log::warning('FlexiblePalletSlipParser vision page failed', [
                    'page' => $index + 1,
                    'error' => $response->error,
                ]);
                continue;
            }
            $lines = array_merge($lines, $this->parseJsonLines($response->content));
        }

        Log::info('FlexiblePalletSlipParser vision extraction complete', ['lines' => count($lines)]);
        return $lines;
    }

    /** @return list<string> */
    private function pdfToImages(string $pdfPath): array
    {
        if (extension_loaded('imagick')) {
            $im = new \Imagick();
            $im->setResolution(150, 150);
            // Cap at six pages for one job to keep vision workloads bounded.
            $im->readImage("{$pdfPath}[0-5]");
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
        if (! $binary) throw new \RuntimeException('Scanned PDF requires Imagick or pdftoppm for vision conversion.');

        $prefix = sys_get_temp_dir() . '/manifest_' . uniqid();
        $process = new Process([$binary, '-r', '150', '-png', '-f', '1', '-l', '6', $pdfPath, $prefix]);
        $process->setTimeout(180);
        $process->run();
        if (! $process->isSuccessful()) throw new \RuntimeException('PDF image conversion failed.');

        $files = glob($prefix . '-*.png') ?: [];
        natsort($files);
        $images = [];
        foreach ($files as $file) {
            if (is_file($file) && filesize($file) > 0) $images[] = base64_encode((string) file_get_contents($file));
            @unlink($file);
        }
        return $images;
    }

    /** @return list<array<string,mixed>> */
    private function parseJsonLines(string $text): array
    {
        $direct = json_decode($text, true);
        if (is_array($direct)) return $this->normalizeLines($direct['lines'] ?? $direct);
        if (preg_match('/\{.*"lines"\s*:\s*\[.*?\]\s*\}/s', $text, $m)) {
            $parsed = json_decode($m[0], true);
            if (is_array($parsed)) return $this->normalizeLines($parsed['lines'] ?? []);
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

            $caseCount = max(1, (int) round($this->number($item['case_count'] ?? $item['cases'] ?? $item['qty'] ?? $item['quantity'] ?? 1)));
            $quantityPerCase = max(1, (int) round($this->number($item['quantity_per_case'] ?? $item['units_per_case'] ?? $item['pack_qty'] ?? 1)));

            $out[] = [
                'description' => $description,
                'case_count' => $caseCount,
                'quantity_per_case' => $quantityPerCase,
                'unit_cost' => $this->money($item['unit_cost'] ?? $item['price'] ?? null),
                'sku' => $this->identifier($item['sku'] ?? $item['item_number'] ?? null),
                'barcode' => $this->identifier($item['barcode'] ?? $item['upc'] ?? $item['ean'] ?? null),
            ];
        }
        return $out;
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

    private function identifier(mixed $value): ?string
    {
        if ($value === null) return null;
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }
}
