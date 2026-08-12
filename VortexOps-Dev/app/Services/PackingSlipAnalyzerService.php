<?php

namespace App\Services;

use App\Models\InventoryItem;
use App\Services\AI\OllamaClient;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

class PackingSlipAnalyzerService
{
    public function __construct(
        private readonly OllamaClient $ollamaClient,
    ) {}

    /**
     * Analyze packing slip file and extract product information.
     * Returns structured data with matched and suggested items.
     */
    public function analyzePackingSlip(string $filePath): array
    {
        $fullPath = Storage::disk('public')->path($filePath);

        if (!file_exists($fullPath)) {
            throw new \RuntimeException("Packing slip file not found: {$fullPath}");
        }

        $fileExtension = pathinfo($fullPath, PATHINFO_EXTENSION);

        // Convert to base64 for AI processing
        if (strtolower($fileExtension) === 'pdf') {
            $base64Image = $this->pdfToBase64Image($fullPath);
        } else {
            $base64Image = base64_encode(file_get_contents($fullPath));
        }

        // Extract product info using vision AI
        $extractedData = $this->extractProductsFromImage($base64Image);

        // Match against existing inventory
        $matched = $this->matchExistingItems($extractedData['products']);
        $suggested = $this->suggestNewItems($extractedData['products'], $matched);

        return [
            'status'           => 'success',
            'matched_items'    => $matched,
            'suggested_items'  => $suggested,
            'raw_extraction'   => $extractedData,
            'total_matched'    => count($matched),
            'total_suggested'  => count($suggested),
        ];
    }

    /**
     * Convert PDF to base64-encoded image (first page).
     * Uses ImageMagick if available, otherwise returns PDF as base64.
     */
    private function pdfToBase64Image(string $pdfPath): string
    {
        // Try to convert first page to PNG using ImageMagick
        $tempImage = sys_get_temp_dir() . '/packing_slip_' . uniqid() . '.png';

        try {
            $process = new Process([
                'convert',
                $pdfPath . '[0]',  // First page only
                '-density', '150',
                $tempImage,
            ]);
            $process->setTimeout(30);
            $process->run();

            if ($process->isSuccessful() && file_exists($tempImage)) {
                $base64 = base64_encode(file_get_contents($tempImage));
                @unlink($tempImage);
                return $base64;
            }
        } catch (\Exception $e) {
            Log::warning("PDF to image conversion failed: {$e->getMessage()}");
        }

        // Fallback: return PDF as base64
        return base64_encode(file_get_contents($pdfPath));
    }

    /**
     * Use vision AI to extract product information from image.
     */
    private function extractProductsFromImage(string $base64Image): array
    {
        $prompt = <<<'PROMPT'
Analyze this packing slip image and extract all products listed.

For each product, extract:
- Product name/description
- SKU or part number (if visible)
- Quantity
- Unit cost (if visible)
- Category or type (if inferrable)

Return the data as a JSON array with this structure:
{
  "products": [
    {
      "name": "Product Name",
      "sku": "SKU-123 or null",
      "quantity": 10,
      "unit_cost": 12.50 or null,
      "category": "Category or null"
    }
  ],
  "vendor": "Vendor name if visible or null",
  "po_number": "PO number if visible or null",
  "date": "Date if visible or null"
}

Return ONLY valid JSON, no other text.
PROMPT;

        try {
            $response = $this->ollamaClient->vision($prompt, $base64Image, ['timeout' => 120]);
            $data = json_decode($response, true);

            if (json_last_error() === JSON_ERROR_NONE && isset($data['products'])) {
                return $data;
            }

            Log::warning("Vision response wasn't valid JSON: {$response}");
            return ['products' => [], 'vendor' => null, 'po_number' => null, 'date' => null];
        } catch (\Exception $e) {
            Log::error("Packing slip vision analysis failed: {$e->getMessage()}");
            throw $e;
        }
    }

    /**
     * Match extracted products against existing inventory items.
     * Returns array of matched items with confidence scores.
     */
    private function matchExistingItems(array $products): array
    {
        $matched = [];

        foreach ($products as $product) {
            $existingItem = null;
            $confidence = 0;

            // Try exact SKU match first (highest confidence)
            if ($product['sku']) {
                $existingItem = InventoryItem::where('sku', $product['sku'])
                    ->orWhere('barcode', $product['sku'])
                    ->first();
                if ($existingItem) {
                    $confidence = 100;
                }
            }

            // Try name matching if no SKU match
            if (!$existingItem && $product['name']) {
                $existingItem = InventoryItem::where('name', 'like', '%' . trim($product['name']) . '%')
                    ->first();
                if ($existingItem) {
                    $confidence = 75;
                }
            }

            // Try partial name match
            if (!$existingItem && $product['name']) {
                $keywords = array_filter(explode(' ', $product['name']));
                foreach ($keywords as $keyword) {
                    if (strlen($keyword) > 3) {
                        $existingItem = InventoryItem::where('name', 'like', '%' . $keyword . '%')
                            ->first();
                        if ($existingItem) {
                            $confidence = 50;
                            break;
                        }
                    }
                }
            }

            if ($existingItem) {
                $matched[] = [
                    'extracted_name'   => $product['name'],
                    'extracted_sku'    => $product['sku'],
                    'extracted_qty'    => $product['quantity'],
                    'extracted_cost'   => $product['unit_cost'],
                    'item_id'          => $existingItem->id,
                    'item_name'        => $existingItem->name,
                    'item_sku'         => $existingItem->sku,
                    'current_cost'     => (float) $existingItem->average_cost,
                    'confidence'       => $confidence,
                ];
            }
        }

        return $matched;
    }

    /**
     * Suggest new items for products that weren't matched to existing inventory.
     */
    private function suggestNewItems(array $products, array $matched): array
    {
        $matchedNames = array_map(fn ($m) => strtolower($m['extracted_name']), $matched);
        $suggested = [];

        foreach ($products as $product) {
            if (!in_array(strtolower($product['name']), $matchedNames)) {
                $suggested[] = [
                    'name'     => $product['name'],
                    'sku'      => $product['sku'] ?? '',
                    'category' => $product['category'] ?? 'Uncategorized',
                    'unit_cost'=> $product['unit_cost'],
                    'qty'      => $product['quantity'],
                ];
            }
        }

        return $suggested;
    }

    /**
     * Create new inventory items from suggested items.
     */
    public function createItemsFromSuggestions(array $suggestions, int $createdBy): array
    {
        $created = [];

        foreach ($suggestions as $suggestion) {
            try {
                $item = InventoryItem::create([
                    'name'            => $suggestion['name'],
                    'sku'             => $suggestion['sku'] ?? null,
                    'category'        => $suggestion['category'],
                    'status'          => 'active',
                    'average_cost'    => $suggestion['unit_cost'] ?? 0,
                    'reorder_level'   => $suggestion['qty'] ?? 10,
                    'created_by'      => $createdBy,
                ]);

                $created[] = [
                    'id'   => $item->id,
                    'name' => $item->name,
                    'sku'  => $item->sku,
                ];
            } catch (\Exception $e) {
                Log::error("Failed to create item from suggestion: {$e->getMessage()}", $suggestion);
            }
        }

        return $created;
    }
}
