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
 *   Stage 1 — Alias / exact lookup    (0ms, confidence ≥ 1.0)
 *   Stage 2 — Fuzzy text matching     (5–20ms, Jaccard + field bonuses)
 *   Stage 3 — Embedding similarity    (10–50ms, cosine similarity via Ollama)
 *   Stage 4 — LLM confirmation        (500ms–5s, Ollama generate with catalogue)
 *
 * Auto-stops at the first stage that returns confidence ≥ 0.95.
 * Stage 4 is only reached when stages 1–3 all fail to find a confident match.
 *
 * Every confirmed match is saved as a ProductIdentity alias so future
 * identical descriptions skip AI entirely (the knowledge base effect).
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
     * @param string      $description Free-text item description
     * @param string|null $upc         UPC/barcode if available
     * @param int|null    $vendorId    Scopes alias lookup to a specific vendor
     */
    /**
     * @param bool $skipLlm Skip the slow Stage 4 LLM call (use for web-request contexts).
     */
    public function match(string $description, ?string $upc = null, ?int $vendorId = null, bool $skipLlm = false): MappingResult
    {
        // Stages 1–3: delegate to the existing three-stage service
        $upstream = $this->upstream->match($description, $upc, $vendorId);

        if ($upstream['product'] && $upstream['confidence'] >= 0.95) {
            return $this->fromUpstream($upstream);
        }

        if ($skipLlm) {
            return $this->fromUpstream($upstream);
        }

        // Stage 4: LLM confirmation when all faster stages fall short
        $llmResult = $this->llmMatch($description, $upstream['candidates']);

        if ($llmResult) {
            return $llmResult;
        }

        return $this->fromUpstream($upstream);
    }

    /**
     * Record a human-confirmed match as a learned alias.
     * Future identical descriptions will resolve in Stage 1 (instant, 100% confidence).
     */
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

    // ── Stage 4: LLM ──────────────────────────────────────────────────────────

    private function llmMatch(string $description, array $candidates): ?MappingResult
    {
        // Build a short catalogue: prefer candidates from earlier stages, else sample from DB
        $items = $this->buildCatalogue($candidates, $description);

        if (empty($items)) {
            return null;
        }

        $itemList = collect($items)
            ->map(fn ($i) => "[{$i['id']}] {$i['name']}" . ($i['sku'] ? " (SKU: {$i['sku']})" : '') . ($i['category'] ? " [{$i['category']}]" : ''))
            ->join("\n");

        $prompt = <<<PROMPT
You are matching a sold item description to an inventory catalogue for a sports card break business.

Inventory items:
{$itemList}

Sold item: "{$description}"

Reply with JSON only, no explanation:
{"item_id": <integer or null>, "confidence": "high|medium|low", "reason": "<one sentence>"}

If no match is reasonable, set item_id to null.
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

            $score = match ($confidence) {
                'high'   => 0.88,
                'medium' => 0.75,
                default  => 0.60,
            };

            return new MappingResult(
                product:    $product,
                confidence: $score,
                stage:      'llm',
                reasons:    ["LLM: {$reason}", "Confidence level: {$confidence}"],
                candidates: array_slice($candidates, 0, 5),
                identity:   null,
            );
        } catch (\Throwable $e) {
            Log::warning("MappingEngine LLM stage failed for '{$description}': {$e->getMessage()}");
            return null;
        }
    }

    /**
     * Build a focused catalogue for the LLM prompt.
     * Priority: stage 1-3 candidates → embedding-similar products → cached random pool.
     */
    private function buildCatalogue(array $candidates, string $description): array
    {
        $items = collect($candidates)
            ->filter(fn ($c) => $c['product'] !== null)
            ->map(fn ($c) => [
                'id'       => $c['product']->id,
                'name'     => $c['product']->name,
                'sku'      => $c['product']->sku ?? null,
                'category' => $c['product']->category ?? null,
            ])
            ->unique('id')
            ->values()
            ->toArray();

        if (count($items) < 20) {
            $existingIds = array_column($items, 'id');

            // Fill with embedding-similar products when possible
            $queryVec = $this->embedding->embed($description);
            if ($queryVec) {
                $catalog = array_filter(
                    $this->embedding->productEmbeddingCatalog(),
                    fn ($id) => ! in_array($id, $existingIds),
                    ARRAY_FILTER_USE_KEY
                );
                $ranked = $this->embedding->rankBySimilarity($queryVec, $catalog, 20 - count($items));
                if (! empty($ranked)) {
                    $embProducts = Product::whereIn('id', array_keys($ranked))
                        ->where('is_active', true)
                        ->get(['id', 'name', 'sku', 'category'])
                        ->keyBy('id');
                    foreach (array_keys($ranked) as $id) {
                        if (count($items) >= 20) {
                            break;
                        }
                        if (isset($embProducts[$id])) {
                            $p           = $embProducts[$id];
                            $items[]     = ['id' => $p->id, 'name' => $p->name, 'sku' => $p->sku, 'category' => $p->category];
                            $existingIds[] = $p->id;
                        }
                    }
                }
            }

            // Fall back to a cached random pool for any remaining slots
            if (count($items) < 20) {
                $pool = Cache::remember('mapping:catalogue_pool', 300, fn () =>
                    Product::where('is_active', true)
                        ->inRandomOrder()
                        ->limit(100)
                        ->get(['id', 'name', 'sku', 'category'])
                        ->map(fn ($p) => ['id' => $p->id, 'name' => $p->name, 'sku' => $p->sku, 'category' => $p->category])
                        ->toArray()
                );
                foreach ($pool as $p) {
                    if (count($items) >= 20) {
                        break;
                    }
                    if (! in_array($p['id'], $existingIds)) {
                        $items[]     = $p;
                        $existingIds[] = $p['id'];
                    }
                }
            }
        }

        return $items;
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    private function fromUpstream(array $upstream): MappingResult
    {
        return new MappingResult(
            product:    $upstream['product'],
            confidence: $upstream['confidence'],
            stage:      $upstream['stage'],
            reasons:    $upstream['reasons'],
            candidates: $upstream['candidates'],
            identity:   $upstream['identity'] ?? null,
        );
    }
}
