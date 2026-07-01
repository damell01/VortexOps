<?php

namespace App\Jobs;

use App\Models\AiTask;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ParsePalletSlipJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 300;
    public int $tries   = 1;

    public function __construct(
        public readonly int    $palletId,
        public readonly int    $aiTaskId,
        public readonly string $storedPath,
    ) {}

    public function handle(): void
    {
        $task = AiTask::findOrFail($this->aiTaskId);
        $task->markProcessing();

        try {
            $images   = $this->toBase64Images($this->storedPath);
            $lines    = [];

            foreach ($images as $b64) {
                $batch = $this->extractFromImage($b64);
                $lines = array_merge($lines, $batch);
            }

            @unlink($this->storedPath);

            $task->markCompleted(['lines' => $lines]);

        } catch (\Throwable $e) {
            @unlink($this->storedPath);
            Log::error('ParsePalletSlipJob failed', ['error' => $e->getMessage(), 'task' => $this->aiTaskId]);
            $task->markFailed($e->getMessage());
        }
    }

    /**
     * Returns a list of base64-encoded PNG/JPEG strings — one per page for PDF,
     * or just the single image for JPEG/PNG.
     *
     * @return list<string>
     */
    private function toBase64Images(string $path): array
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if ($ext === 'pdf') {
            return $this->pdfToImages($path);
        }

        // Regular image — just base64-encode as-is
        return [base64_encode(file_get_contents($path))];
    }

    /** @return list<string> base64 PNGs, one per page */
    private function pdfToImages(string $pdfPath): array
    {
        // Requires Imagick (ImageMagick PHP extension) or pdftoppm (poppler-utils).
        // We try Imagick first since it's commonly available in PHP containers.

        if (extension_loaded('imagick')) {
            $im = new \Imagick();
            $im->setResolution(150, 150);
            $im->readImage("{$pdfPath}[0-9]"); // up to 10 pages
            $images = [];
            foreach ($im as $page) {
                $page->setImageFormat('png');
                $page->setImageAlphaChannel(\Imagick::ALPHACHANNEL_REMOVE);
                $page->mergeImageLayers(\Imagick::LAYERMETHOD_FLATTEN);
                $images[] = base64_encode($page->getImageBlob());
            }
            $im->clear();
            return $images ?: []; // fallback handled below
        }

        // Fallback: pdftoppm (poppler-utils) — convert first page only
        $tmpPng = sys_get_temp_dir() . '/slip_' . uniqid() . '.png';
        $cmd    = sprintf('pdftoppm -r 150 -png -f 1 -l 1 %s %s 2>/dev/null', escapeshellarg($pdfPath), escapeshellarg(rtrim($tmpPng, '.png')));
        exec($cmd);

        // pdftoppm appends -1.png
        $generated = rtrim($tmpPng, '.png') . '-1.png';
        if (file_exists($generated)) {
            $b64 = base64_encode(file_get_contents($generated));
            @unlink($generated);
            return [$b64];
        }

        throw new \RuntimeException('PDF conversion requires the Imagick PHP extension or poppler-utils (pdftoppm). Neither was found.');
    }

    /** @return list<array{description:string,case_count:int,unit_cost:float|null,sku:string|null,barcode:string|null}> */
    private function extractFromImage(string $base64Image): array
    {
        $baseUrl     = rtrim(config('services.ollama.url', 'http://ollama:11434'), '/');
        $visionModel = config('services.ollama.vision_model', 'moondream');

        $prompt = <<<'PROMPT'
Look at this packing slip or invoice image. Extract every product line item you can read.
Return ONLY a valid JSON object — no explanation, no markdown, no extra text — in exactly this format:
{"lines":[{"description":"full product name","case_count":1,"unit_cost":89.99,"sku":"ABC123","barcode":"012345678901"}]}
Rules:
- description: full product name or description (required)
- case_count: number of cases, units, or quantity as an integer (use 1 if not shown)
- unit_cost: price per unit as a plain number, no $ sign (use null if not shown)
- sku: item number, SKU, or part number (use null if not shown)
- barcode: UPC, EAN, or barcode number (use null if not shown)
Include every line item you can read. If the slip has multiple pages visible, include all.
PROMPT;

        $response = Http::timeout(240)->post("{$baseUrl}/api/generate", [
            'model'  => $visionModel,
            'prompt' => $prompt,
            'images' => [$base64Image],
            'stream' => false,
        ]);

        if (! $response->successful()) {
            throw new \RuntimeException("Ollama vision returned HTTP {$response->status()}. Is the vision model ('{$visionModel}') pulled?");
        }

        $text = $response->json('response', '');

        return $this->parseJsonLines($text);
    }

    /**
     * Extract JSON from the model response, tolerating surrounding text.
     *
     * @return list<array{description:string,case_count:int,unit_cost:float|null,sku:string|null,barcode:string|null}>
     */
    private function parseJsonLines(string $text): array
    {
        // Try direct decode first
        $direct = json_decode($text, true);
        if (isset($direct['lines']) && is_array($direct['lines'])) {
            return $this->normalizeLines($direct['lines']);
        }

        // Try to extract JSON object from text
        if (preg_match('/\{.*"lines"\s*:\s*\[.*?\]\s*\}/s', $text, $m)) {
            $parsed = json_decode($m[0], true);
            if (isset($parsed['lines']) && is_array($parsed['lines'])) {
                return $this->normalizeLines($parsed['lines']);
            }
        }

        // Last resort: find any JSON array of objects
        if (preg_match('/\[.*\]/s', $text, $m)) {
            $parsed = json_decode($m[0], true);
            if (is_array($parsed)) {
                return $this->normalizeLines($parsed);
            }
        }

        // Return empty with the raw text so user can see what happened
        return [];
    }

    /** @return list<array{description:string,case_count:int,unit_cost:float|null,sku:string|null,barcode:string|null}> */
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
