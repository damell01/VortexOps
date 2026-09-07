<?php

namespace App\Services\AI\Mapping;

use App\Models\Product;
use App\Models\ProductIdentity;
use App\Services\AI\OllamaClient;
use App\Services\EmbeddingService;
use App\Services\ProductMatchingService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Four-stage product matching pipeline.
 *
 *   Stage 1 — Alias / exact lookup    (PHP/SQL)
 *   Stage 2 — Fuzzy text matching     (PHP)
 *   Stage 3 — Embedding similarity    (Ollama)
 *   Stage 4 — LLM confirmation        (Ollama)
 *
 * In low-resource/background-only mode a caller using $skipLlm=true skips BOTH
 * model-backed stages. That is the mode used by Livewire/Filament verification,
 * ensuring a cold Ollama model can never lengthen an ordinary web request.
 */
class MappingEngine
{
    public function __construct(
        private readonly ProductMatchingService $upstream,
        private readonly OllamaClient $ollama,
        private readonly EmbeddingService $embedding,
    ) {}

    /**
     * Match a vendor/sales description to a Product.
     *
     * @param bool $skipLlm Web-request mode. With background_only enabled this
     *                      suppresses embeddings as well as the final LLM stage.
     */
    public function match(string $description, ?string $upc = null, ?int $vendorId = null, bool $skipLlm = false): MappingResult
    {
        $skipEmbedding = $skipLlm && config('ai.ops.background_only', true);

        $upstream = $this->upstream->match(
            $description,
            $upc,
            $vendorId,
            skipEmbedding: $skipEmbedding,
        );

        if ($upstream['product'] && $upstream['confidence'] >= 0.95) {
            return $this->fromUpstream($upstream);
        }

        if ($skipLlm) {
            return $this->fromUpstream($upstream);
        }

        $llmResult = $this->llmMatch($description, $upstream['candidates']);

        if ($llmResult) {
            return $llmResult;
        }

        return $this->fromUpstream($upstream);
    }

    public function confirmMatch(
        string $vendorDescription,
        Product $product,
        MappingResult $result,
        int $confirmedByUserId,
        ?int $vendorId = null,
    ): ProductIdentity {
        return $this->upstream->confirmMatch(
            $vendorDescription,
            $product,
            $result->confidence,
            $result->stage,
            $confirmedByUserId,
            $vendorId,
        );
    }

    private function llmMatch(string $description, array $candidates): ?MappingResult
    {
        $items = $this->buildCatalogue($candidates, $description);

        if (empty($items)) {
            return null;
        }

        $itemList = collect($items)
            ->map(function ($i) {
                $meta = array_values(array_filter([
                    $i['category'] ?? null,
                    $i['product_type'] ?? null,
                    $i['configuration'] ?? null,
                ]));

                return "[{$i['id']}] {$i['name']}"
                    . ($i['sku'] ? " (SKU: {$i['sku']})" : '')
                    . ($meta ? ' [' . implode(' · ', $meta) . ']' : '');
            })
            ->join("\n");

        $prompt = <<<PROMPT
You are matching a sold item description to an inventory catalogue for a sports card break business.

Inventory items:
{$itemList}

Sold item: "{$description}"

Reply with JSON only, no explanation:
{"item_id": <integer or null>, "confidence": "high|medium|low", "reason": "<one sentence>"}

Packaging and configuration words are strict. Do not match different explicit formats such as Booster vs Jumbo, Hobby vs Blaster, Box vs Case, Pack vs Box, or ETB vs Booster. If no configuration-compatible match is reasonable, set item_id to null.
PROMPT;

        try {
            $raw  = $this->ollama->generate($prompt, ['timeout' => 60, 'format' => 'json']);
            $json = json_decode($raw, true);

            $itemId     = $json['item_id'] ?? null;
            $confidence = $json['confidence'] ?? 'low';
            $reason     = $json['reason'] ?? '';

            if (! $itemId) {
                return null;
            }

            $product = Product::find($itemId);
            if (! $product) {
                return null;
            }

            // Never allow the model to override a concrete packaging conflict.
            // This is intentionally deterministic even when the model says the
            // semantic match is high confidence.
            if (! $this->upstream->isConfigurationCompatible($description, $product)) {
                Log::info('MappingEngine rejected LLM configuration mismatch', [
                    'description' => $description,
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                ]);
                return null;
            }

            $score = match ($confidence) {
                'high'   => 0.88,
                'medium' => 0.75,
                default  => 0.60,
            };

            return new MappingResult(
                product: $product,
                confidence: $score,
                stage: 'llm',
                reasons: ["LLM: {$reason}", "Confidence level: {$confidence}"],
                candidates: array_slice($candidates, 0, 5),
                identity: null,
            );
        } catch (\Throwable $e) {
            Log::warning("MappingEngine LLM stage failed for '{$description}': {$e->getMessage()}");
            return null;
        }
    }

    /**
     * Build a focused catalogue for background/explicit LLM matching only.
     */
    private function buildCatalogue(array $candidates, string $description): array
    {
        $items = collect($candidates)
            ->filter(fn ($c) => ($c['product'] ?? null) !== null)
            ->filter(fn ($c) => $this->upstream->isConfigurationCompatible($description, $c['product']))
            ->map(fn ($c) => $this->catalogueItem($c['product']))
            ->unique('id')
            ->values()
            ->toArray();

        if (count($items) < 20) {
            $existingIds = array_column($items, 'id');
            $queryVec = $this->embedding->embed($description);

            if ($queryVec) {
                $catalog = array_filter(
                    $this->embedding->productEmbeddingCatalog(),
                    fn ($id) => ! in_array($id, $existingIds),
                    ARRAY_FILTER_USE_KEY
                );
                // Ask for more than the remaining slot count because hard
                // configuration filtering may reject several semantic neighbors.
                $ranked = $this->embedding->rankBySimilarity($queryVec, $catalog, 60);
                if (! empty($ranked)) {
                    $embProducts = Product::whereIn('id', array_keys($ranked))
                        ->where('is_active', true)
                        ->get(['id', 'name', 'sku', 'category', 'product_type', 'configuration'])
                        ->keyBy('id');
                    foreach (array_keys($ranked) as $id) {
                        if (count($items) >= 20) break;
                        if (! isset($embProducts[$id])) continue;
                        $p = $embProducts[$id];
                        if (! $this->upstream->isConfigurationCompatible($description, $p)) continue;
                        $items[] = $this->catalogueItem($p);
                        $existingIds[] = $p->id;
                    }
                }
            }

            if (count($items) < 20) {
                $pool = Cache::remember('mapping:catalogue_pool', 300, fn () =>
                    Product::where('is_active', true)
                        ->inRandomOrder()
                        ->limit(100)
                        ->get(['id', 'name', 'sku', 'category', 'product_type', 'configuration'])
                        ->map(fn ($p) => $this->catalogueItem($p))
                        ->toArray()
                );
                foreach ($pool as $item) {
                    if (count($items) >= 20) break;
                    if (in_array($item['id'], $existingIds)) continue;

                    $p = Product::find($item['id']);
                    if (! $p || ! $this->upstream->isConfigurationCompatible($description, $p)) continue;

                    $items[] = $item;
                    $existingIds[] = $item['id'];
                }
            }
        }

        return $items;
    }

    private function catalogueItem(Product $product): array
    {
        return [
            'id' => $product->id,
            'name' => $product->name,
            'sku' => $product->sku ?? null,
            'category' => $product->category ?? null,
            'product_type' => $product->product_type ?? null,
            'configuration' => $product->configuration ?? null,
        ];
    }

    private function fromUpstream(array $upstream): MappingResult
    {
        return new MappingResult(
            product: $upstream['product'],
            confidence: $upstream['confidence'],
            stage: $upstream['stage'],
            reasons: $upstream['reasons'],
            candidates: $upstream['candidates'],
            identity: $upstream['identity'] ?? null,
        );
    }
}
