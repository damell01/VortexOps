<?php

namespace App\Services\AI\Documents;

use App\AI\Prompts\PromptLibrary;
use App\AI\Services\AiGateway;
use Illuminate\Support\Facades\Log;

/**
 * Parses packing slips and invoices (PDF or image) into structured line items.
 * Vision runs through AiGateway, so the model comes from Settings and the
 * extraction prompt lives in PromptLibrary — this class owns the image handling
 * and JSON normalisation, not the model or the prompt.
 */
class PalletSlipParser
{
    public function __construct(
        private readonly AiGateway     $gateway,
        private readonly PromptLibrary $prompts,
    ) {}

    /**
     * Parse a file (PDF or image) into structured line items.
     * Deletes the file after parsing.
     *
     * @return list<array{description:string,case_count:int,unit_cost:float|null,sku:string|null,barcode:string|null}>
     */
    public function parse(string $storedPath): array
    {
        try {
            $images = $this->toBase64Images($storedPath);
            $lines  = [];

            foreach ($images as $b64) {
                $batch = $this->extractFromImage($b64);
                $lines = array_merge($lines, $batch);
            }

            return $lines;
        } finally {
            @unlink($storedPath);
        }
    }

    // ── Image extraction ──────────────────────────────────────────────────────

    /**
     * @return list<string> Base64-encoded PNG/JPEG strings, one per page
     */
    private function toBase64Images(string $path): array
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if ($ext === 'pdf') {
            return $this->pdfToImages($path);
        }

        if (! file_exists($path)) {
            throw new \RuntimeException("Uploaded file not found: {$path}");
        }

        return [base64_encode(file_get_contents($path))];
    }

    /** @return list<string> Base64 PNGs, one per page */
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

            if (! empty($images)) {
                return $images;
            }
        }

        // Fallback: pdftoppm (poppler-utils)
        $prefix = sys_get_temp_dir() . '/slip_' . uniqid();
        $cmd    = sprintf(
            'pdftoppm -r 150 -png -f 1 -l 1 %s %s 2>/dev/null',
            escapeshellarg($pdfPath),
            escapeshellarg($prefix),
        );
        exec($cmd);

        $generated = $prefix . '-1.png';

        if (file_exists($generated)) {
            $b64 = base64_encode(file_get_contents($generated));
            @unlink($generated);
            return [$b64];
        }

        throw new \RuntimeException(
            'PDF conversion requires the Imagick PHP extension or poppler-utils (pdftoppm). Neither was found.'
        );
    }

    // ── Vision AI ─────────────────────────────────────────────────────────────

    /**
     * @return list<array{description:string,case_count:int,unit_cost:float|null,sku:string|null,barcode:string|null}>
     */
    private function extractFromImage(string $base64Image): array
    {
        $response = $this->gateway->vision($this->prompts->slipExtraction(), $base64Image);

        if (! $response->success) {
            throw new \RuntimeException("Vision extraction failed: {$response->error}");
        }

        return $this->parseJsonLines($response->content);
    }

    /**
     * @return list<array{description:string,case_count:int,unit_cost:float|null,sku:string|null,barcode:string|null}>
     */
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
            if (is_array($parsed)) {
                return $this->normalizeLines($parsed);
            }
        }

        Log::warning('PalletSlipParser: could not extract JSON from vision response', ['sample' => substr($text, 0, 300)]);
        return [];
    }

    /**
     * @return list<array{description:string,case_count:int,unit_cost:float|null,sku:string|null,barcode:string|null}>
     */
    private function normalizeLines(array $raw): array
    {
        $out = [];

        foreach ($raw as $item) {
            if (! is_array($item)) {
                continue;
            }

            $description = trim((string) ($item['description'] ?? $item['name'] ?? $item['item'] ?? ''));

            if ($description === '') {
                continue;
            }

            $out[] = [
                'description' => $description,
                'case_count'  => max(1, (int) ($item['case_count'] ?? $item['qty'] ?? $item['quantity'] ?? 1)),
                'unit_cost'   => isset($item['unit_cost']) && $item['unit_cost'] !== null ? (float) $item['unit_cost'] : null,
                'sku'         => ($item['sku'] ?? null) ? trim((string) $item['sku']) : null,
                'barcode'     => ($item['barcode'] ?? null) ? trim((string) $item['barcode']) : null,
            ];
        }

        return $out;
    }
}
