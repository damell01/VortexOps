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
 */
class ProductMatchingService
{
    public function __construct(
        private readonly EmbeddingService $embedding
    ) {}

    private ?\Illuminate\Database\Eloquent\Collection $catalogMemo = null;

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
            'product'    => $stage2['product'],
            'confidence' => $stage2['confidence'],
            'stage'      => $stage2['product'] ? 'fuzzy' : 'none',
            'reasons'    => $stage2['reasons'],
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

        // Compare normalized names so punctuation / vendor formatting does not
        // prevent what is otherwise an exact catalogue-name match.
        $exact = $this->fuzzyCatalog()->first(
            fn (Product $product) => $this->embedding->normalizeText($product->name) === $normalized
        );
        if ($exact) {
            return $this->result($exact, 1.0, 'alias', null, [], ['Exact normalized product name match']);
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
            // Keep broader candidates for the background AI confirmation stage.
            // A vendor description can contain brand/language filler and still
            // clearly refer to the same inventory item.
            if ($scored['score'] >= 0.35) {
                $scores[$product->id] = [
                    'product' => $product,
                    'score' => $scored['score'],
                    'reasons' => $scored['reasons'],
                ];
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

        // 0.68 is a suggestion threshold, not an automatic commit threshold.
        // The manifest review still requires human approval, while >= .95 is
        // the existing auto-accept confidence boundary used elsewhere.
        if ($top['score'] >= 0.68) {
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

        $coverage = min(
            count($tokens) > 0 ? $intersection / count($tokens) : 0,
            count($productTokens) > 0 ? $intersection / count($productTokens) : 0,
        );

        $yearBonus = 0.0;
        if ($product->year && in_array((string) $product->year, $tokens)) {
            $yearBonus = 0.08;
            $reasons[] = "Year matched ({$product->year})";
        }

        $brandBonus = 0.0;
        if ($product->brand) {
            $brandTokens = $this->tokenize($product->brand);
            if (! empty(array_intersect($tokens, $brandTokens))) {
                $brandBonus = 0.04;
                $reasons[] = "Brand matched ({$product->brand})";
            }
        }

        if ($product->configuration) {
            $configTokens = $this->tokenize($product->configuration);
            if (! empty(array_intersect($tokens, $configTokens))) {
                $reasons[] = "Configuration matched ({$product->configuration})";
            }
        }

        $nameSimilarity = 0.0;
        similar_text(
            implode(' ', $tokens),
            implode(' ', $this->tokenize($product->name)),
            $nameSimilarity
        );
        $nameSimilarity /= 100.0;
        $reasons[] = sprintf('Name similarity: %.1f%%', $nameSimilarity * 100);
        $reasons[] = sprintf('Token coverage: %.1f%%', $coverage * 100);

        $raw = ($jaccard * 0.35)
            + ($coverage * 0.30)
            + ($nameSimilarity * 0.23)
            + $yearBonus
            + $brandBonus;

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
        $aliases = [
            'chinese' => 'ch',
            'china' => 'ch',
            'japanese' => 'jp',
            'japan' => 'jp',
            'indonesian' => 'indo',
            'indonesia' => 'indo',
        ];
        $ignore = ['pokemon', 'version', 'edition', 'product', 'tcg'];

        $tokens = explode(' ', $this->embedding->normalizeText($text));
        $tokens = array_map(fn ($token) => $aliases[$token] ?? $token, $tokens);

        return array_values(array_unique(array_filter(
            $tokens,
            fn ($token) => strlen($token) >= 2 && ! in_array($token, $ignore, true)
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
