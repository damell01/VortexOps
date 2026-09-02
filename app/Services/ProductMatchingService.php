<?php

namespace App\Services;

use App\Jobs\GenerateProductEmbeddingJob;
use App\Models\Product;
use App\Models\ProductIdentity;
use Illuminate\Support\Facades\Cache;

/**
 * Three-stage product matching pipeline:
 *
 *   Stage 1 — Alias lookup (0 ms, 100% confidence)
 *   Stage 2 — Fuzzy text matching (5–20ms, structured token scoring)
 *   Stage 3 — Ollama embedding similarity
 *
 * Callers running inside a web request can set $skipEmbedding=true. That keeps
 * matching entirely in PHP/SQL so a cold or busy local model can never hold up
 * receiving, inventory, or another normal page request.
 */
class ProductMatchingService
{
    public function __construct(
        private readonly EmbeddingService $embedding
    ) {}

    /** Per-request memo of the active-product catalogue (see fuzzyCatalog). */
    private ?\Illuminate\Database\Eloquent\Collection $catalogMemo = null;

    /**
     * @return array{product: ?Product, confidence: float, stage: string, reasons: string[], candidates: array, identity: ?ProductIdentity}
     */
    public function match(
        string $description,
        ?string $upc = null,
        ?int $vendorId = null,
        bool $skipEmbedding = false,
    ): array {
        $stage1 = $this->aliasLookup($description, $upc, $vendorId);
        if ($stage1['product'] && $stage1['confidence'] >= 1.0) {
            return $stage1;
        }

        $stage2 = $this->fuzzyMatch($description, $upc);
        if ($stage2['product'] && $stage2['confidence'] >= 0.95) {
            return $stage2;
        }

        // This is the important low-resource boundary: web-request matching ends
        // here. Fuzzy candidates are still useful for the review UI, while no
        // HTTP request to Ollama/embedding model is made.
        if ($skipEmbedding) {
            return $stage2;
        }

        $stage3 = $this->embeddingMatch($description, $upc);
        if ($stage3['product']) {
            $stage3['candidates'] = array_merge($stage2['candidates'], $stage3['candidates']);
            $stage3['reasons'] = array_merge($stage3['reasons'], $stage2['reasons']);
            return $stage3;
        }

        return [
            'product'    => null,
            'confidence' => 0.0,
            'stage'      => 'none',
            'reasons'    => [],
            'candidates' => array_merge($stage2['candidates'], $stage3['candidates']),
            'identity'   => null,
        ];
    }

    private function aliasLookup(string $description, ?string $upc, ?int $vendorId): array
    {
        $normalized = $this->embedding->normalizeText($description);

        if ($upc) {
            $byUpc = Product::where('upc', $upc)->first()
                ?? ProductIdentity::where('type', ProductIdentity::TYPE_UPC)
                    ->where('value', $upc)
                    ->with('product')
                    ->first()
                    ?->product;

            if ($byUpc) {
                return $this->result($byUpc, 1.0, 'alias', null, [], ['UPC matched exactly']);
            }
        }

        $identity = ProductIdentity::findAlias($normalized, $vendorId)
            ?? ProductIdentity::findAlias($normalized, null);

        if ($identity?->product) {
            $identity->increment('times_confirmed');
            $identity->update(['last_confirmed_at' => now()]);
            $count = $identity->times_confirmed;
            return $this->result($identity->product, 1.0, 'alias', $identity, [], [
                'Alias matched exactly',
                "Learned from {$count} previous " . ($count === 1 ? 'shipment' : 'shipments'),
            ]);
        }

        $exact = Product::whereRaw('LOWER(name) = ?', [$normalized])->first();
        if ($exact) {
            return $this->result($exact, 1.0, 'alias', null, [], ['Exact product name match']);
        }

        return $this->emptyResult();
    }

    private function fuzzyMatch(string $description, ?string $upc): array
    {
        $tokens = $this->tokenize($description);
        if (empty($tokens)) {
            return $this->emptyResult();
        }

        $scores = [];
        foreach ($this->fuzzyCatalog() as $product) {
            $scored = $this->scoreTokensDetailed($tokens, $product);
            if ($scored['score'] > 0.5) {
                $scores[$product->id] = ['product' => $product, 'score' => $scored['score'], 'reasons' => $scored['reasons']];
            }
        }

        if (empty($scores)) {
            return $this->emptyResult('fuzzy');
        }

        uasort($scores, fn ($a, $b) => $b['score'] <=> $a['score']);
        $top = reset($scores);
        $candidates = array_map(
            fn ($s) => ['product' => $s['product'], 'confidence' => round($s['score'], 4)],
            array_slice($scores, 0, 5, true)
        );

        if ($top['score'] >= 0.80) {
            return $this->result($top['product'], round($top['score'], 4), 'fuzzy', null, $candidates, $top['reasons']);
        }

        return $this->emptyResult('fuzzy', $candidates);
    }

    private function scoreTokensDetailed(array $tokens, Product $product): array
    {
        $reasons = [];
        $productText = $this->embedding->normalizeText(implode(' ', array_filter([
            $product->name,
            $product->brand,
            $product->sport,
            $product->year,
            $product->set_name,
            $product->product_type,
            $product->configuration,
        ])));
        $productTokens = $this->tokenize($productText);

        if (empty($productTokens)) {
            return ['score' => 0.0, 'reasons' => []];
        }

        $intersection = count(array_intersect($tokens, $productTokens));
        $union = count(array_unique(array_merge($tokens, $productTokens)));
        $jaccard = $union > 0 ? $intersection / $union : 0.0;

        $yearBonus = 0.0;
        if ($product->year && in_array((string) $product->year, $tokens)) {
            $yearBonus = 0.1;
            $reasons[] = "Year matched ({$product->year})";
        }

        $brandBonus = 0.0;
        if ($product->brand) {
            $brandTokens = $this->tokenize($product->brand);
            if (! empty(array_intersect($tokens, $brandTokens))) {
                $brandBonus = 0.05;
                $reasons[] = "Brand matched ({$product->brand})";
            }
        }

        if ($product->configuration) {
            $configTokens = $this->tokenize($product->configuration);
            if (! empty(array_intersect($tokens, $configTokens))) {
                $reasons[] = "Configuration matched ({$product->configuration})";
            }
        }

        if ($product->sport) {
            $sportTokens = $this->tokenize($product->sport);
            if (! empty(array_intersect($tokens, $sportTokens))) {
                $reasons[] = "Sport matched ({$product->sport})";
            }
        }

        $nameSimilarity = 0.0;
        similar_text(
            implode(' ', $tokens),
            $this->embedding->normalizeText($product->name),
            $nameSimilarity
        );
        $nameSimilarity /= 100.0;
        $reasons[] = sprintf('Name similarity: %.1f%%', $nameSimilarity * 100);

        $raw = ($jaccard * 0.5) + ($nameSimilarity * 0.35) + $yearBonus + $brandBonus;
        return ['score' => min(1.0, $raw), 'reasons' => $reasons];
    }

    private function embeddingMatch(string $description, ?string $upc): array
    {
        $queryEmbedding = $this->embedding->embed($description);
        if (! $queryEmbedding) {
            return $this->emptyResult('embedding');
        }

        $catalog = $this->embedding->productEmbeddingCatalog();
        if (empty($catalog)) {
            return $this->emptyResult('embedding');
        }

        $ranked = $this->embedding->rankBySimilarity($queryEmbedding, $catalog, 5);
        if (empty($ranked)) {
            return $this->emptyResult('embedding');
        }

        $topId = array_key_first($ranked);
        $topScore = $ranked[$topId];
        $productIds = array_keys($ranked);
        $products = Product::whereIn('id', $productIds)->where('is_active', true)->get()->keyBy('id');

        $candidates = array_map(fn ($id, $score) => [
            'product' => $products[$id] ?? null,
            'confidence' => round($score, 4),
        ], $productIds, $ranked);
        $candidates = array_filter($candidates, fn ($c) => $c['product'] !== null);

        if ($topScore >= 0.80 && isset($products[$topId])) {
            return $this->result(
                $products[$topId],
                round($topScore, 4),
                'embedding',
                null,
                array_values($candidates),
                [sprintf('Embedding similarity: %.1f%%', $topScore * 100)],
            );
        }

        return $this->emptyResult('embedding', array_values($candidates));
    }

    /**
     * Record a human-confirmed match. The alias is immediate and deterministic.
     * Embedding enrichment is pushed to the AI worker in background-only mode.
     */
    public function confirmMatch(
        string $vendorDescription,
        Product $product,
        float $confidence,
        string $stage,
        int $confirmedByUserId,
        ?int $vendorId = null
    ): ProductIdentity {
        $normalized = $this->embedding->normalizeText($vendorDescription);
        $identity = ProductIdentity::recordAlias($product, $normalized, $confidence, $stage, $vendorId);
        $identity->recordConfirmation($confirmedByUserId);

        if (in_array($stage, ['embedding', 'llm'], true)) {
            if (config('ai.ops.background_only', true)) {
                // The learned alias works immediately. Generate/rebuild the
                // product embedding later without holding this request open.
                GenerateProductEmbeddingJob::dispatch($product->id)
                    ->onQueue((string) config('ai.ops.queue', 'ai'));
            } else {
                $emb = $this->embedding->embed($normalized);
                if ($emb) {
                    $identity->update(['embedding' => $emb]);
                }
            }
        }

        $this->embedding->flushCatalogCache();
        $this->flushFuzzyCatalog();

        return $identity;
    }

    private function fuzzyCatalog(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->catalogMemo ??= Product::where('is_active', true)
            ->select(['id', 'name', 'brand', 'sport', 'year', 'set_name', 'product_type', 'configuration', 'upc'])
            ->get();
    }

    public function flushFuzzyCatalog(): void
    {
        $this->catalogMemo = null;
        Cache::forget('matching:fuzzy_catalog');
    }

    private function tokenize(string $text): array
    {
        return array_values(array_unique(array_filter(
            explode(' ', $this->embedding->normalizeText($text)),
            fn ($t) => strlen($t) >= 2
        )));
    }

    private function result(Product $product, float $confidence, string $stage, ?ProductIdentity $identity, array $candidates = [], array $reasons = []): array
    {
        return compact('product', 'confidence', 'stage', 'identity', 'candidates', 'reasons');
    }

    private function emptyResult(string $stage = 'none', array $candidates = []): array
    {
        return ['product' => null, 'confidence' => 0.0, 'stage' => $stage, 'reasons' => [], 'candidates' => $candidates, 'identity' => null];
    }
}
