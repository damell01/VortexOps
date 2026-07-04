<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductIdentity;
use App\Models\Vendor;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Three-stage product matching pipeline:
 *
 *   Stage 1 — Alias lookup (0 ms, 100% confidence)
 *   Stage 2 — Fuzzy text matching (5–20 ms, structured token scoring)
 *   Stage 3 — Ollama embedding similarity + optional LLM confirmation
 *
 * Confidence thresholds:
 *   >= 1.00  exact alias match
 *   >= 0.95  auto-accept (no human review)
 *   >= 0.80  needs review
 *   <  0.80  new product candidate
 */
class ProductMatchingService
{
    public function __construct(
        private readonly EmbeddingService $embedding
    ) {}

    // ── Match result ───────────────────────────────────────────────────────────

    /**
     * @return array{product: ?Product, confidence: float, stage: string, candidates: array, identity: ?ProductIdentity}
     */
    public function match(string $description, ?string $upc = null, ?int $vendorId = null): array
    {
        // Stage 1: exact alias / identity lookup
        $stage1 = $this->aliasLookup($description, $upc, $vendorId);
        if ($stage1['product'] && $stage1['confidence'] >= 1.0) {
            return $stage1;
        }

        // Stage 2: fuzzy text match
        $stage2 = $this->fuzzyMatch($description, $upc);
        if ($stage2['product'] && $stage2['confidence'] >= 0.95) {
            return $stage2;
        }

        // Stage 3: embedding similarity
        $stage3 = $this->embeddingMatch($description, $upc);
        if ($stage3['product']) {
            // Merge candidates from stage 2 and 3 for the review UI
            $stage3['candidates'] = array_merge($stage2['candidates'], $stage3['candidates']);
            return $stage3;
        }

        // Nothing found — return stage 2 candidates (best fuzzy guesses) for the UI
        return [
            'product'    => null,
            'confidence' => 0.0,
            'stage'      => 'none',
            'candidates' => array_merge($stage2['candidates'], $stage3['candidates']),
            'identity'   => null,
        ];
    }

    // ── Stage 1: Alias lookup ──────────────────────────────────────────────────

    private function aliasLookup(string $description, ?string $upc, ?int $vendorId): array
    {
        $normalized = $this->embedding->normalizeText($description);

        // Direct UPC lookup
        if ($upc) {
            $byUpc = Product::where('upc', $upc)->first()
                ?? ProductIdentity::where('type', ProductIdentity::TYPE_UPC)
                    ->where('value', $upc)
                    ->with('product')
                    ->first()
                    ?->product;

            if ($byUpc) {
                return $this->result($byUpc, 1.0, 'alias', null);
            }
        }

        // Alias table lookup (vendor-scoped first, then global)
        $identity = ProductIdentity::findAlias($normalized, $vendorId)
            ?? ProductIdentity::findAlias($normalized, null);

        if ($identity?->product) {
            $identity->increment('times_confirmed');
            $identity->update(['last_confirmed_at' => now()]);
            return $this->result($identity->product, 1.0, 'alias', $identity);
        }

        // Exact name match as fallback
        $exact = Product::whereRaw('LOWER(name) = ?', [$normalized])->first();
        if ($exact) {
            return $this->result($exact, 1.0, 'alias', null);
        }

        return $this->emptyResult();
    }

    // ── Stage 2: Fuzzy matching ────────────────────────────────────────────────

    private function fuzzyMatch(string $description, ?string $upc): array
    {
        $tokens = $this->tokenize($description);

        if (empty($tokens)) {
            return $this->emptyResult();
        }

        $products = Product::where('is_active', true)
            ->select(['id', 'name', 'brand', 'sport', 'year', 'set_name', 'product_type', 'configuration', 'upc'])
            ->get();

        $scores = [];

        foreach ($products as $product) {
            $score = $this->scoreTokens($tokens, $product);
            if ($score > 0.5) {
                $scores[$product->id] = ['product' => $product, 'score' => $score];
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
            return $this->result($top['product'], round($top['score'], 4), 'fuzzy', null, $candidates);
        }

        return $this->emptyResult('fuzzy', $candidates);
    }

    /**
     * Score a product against tokenized query. Returns 0.0–1.0.
     */
    private function scoreTokens(array $tokens, Product $product): float
    {
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
            return 0.0;
        }

        // Token overlap score
        $intersection = count(array_intersect($tokens, $productTokens));
        $union        = count(array_unique(array_merge($tokens, $productTokens)));
        $jaccard      = $union > 0 ? $intersection / $union : 0.0;

        // Bonus: year exact match
        $yearBonus = 0.0;
        if ($product->year) {
            $yearBonus = in_array((string) $product->year, $tokens) ? 0.1 : 0.0;
        }

        // Bonus: brand token present
        $brandBonus = 0.0;
        if ($product->brand) {
            $brandTokens = $this->tokenize($product->brand);
            $brandBonus  = ! empty(array_intersect($tokens, $brandTokens)) ? 0.05 : 0.0;
        }

        // Levenshtein bonus against product name
        $nameSimilarity = 0.0;
        similar_text(
            implode(' ', $tokens),
            $this->embedding->normalizeText($product->name),
            $nameSimilarity
        );
        $nameSimilarity /= 100.0;

        $raw = ($jaccard * 0.5) + ($nameSimilarity * 0.35) + $yearBonus + $brandBonus;
        return min(1.0, $raw);
    }

    // ── Stage 3: Embedding similarity ─────────────────────────────────────────

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

        $topId    = array_key_first($ranked);
        $topScore = $ranked[$topId];

        $productIds = array_keys($ranked);
        $products   = Product::whereIn('id', $productIds)->get()->keyBy('id');

        $candidates = array_map(fn ($id, $score) => [
            'product'    => $products[$id] ?? null,
            'confidence' => round($score, 4),
        ], $productIds, $ranked);
        $candidates = array_filter($candidates, fn ($c) => $c['product'] !== null);

        if ($topScore >= 0.80 && isset($products[$topId])) {
            return $this->result($products[$topId], round($topScore, 4), 'embedding', null, array_values($candidates));
        }

        return $this->emptyResult('embedding', array_values($candidates));
    }

    // ── Confirmation / learning ────────────────────────────────────────────────

    /**
     * Record a human-confirmed match. Saves the alias for instant future lookups.
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

        // Persist the embedding for faster future similarity lookups
        if (in_array($stage, ['embedding', 'llm'])) {
            $emb = $this->embedding->embed($normalized);
            if ($emb) {
                $identity->update(['embedding' => $emb]);
            }
        }

        $this->embedding->flushCatalogCache();

        return $identity;
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    private function tokenize(string $text): array
    {
        return array_values(array_unique(array_filter(
            explode(' ', $this->embedding->normalizeText($text)),
            fn ($t) => strlen($t) >= 2
        )));
    }

    private function result(Product $product, float $confidence, string $stage, ?ProductIdentity $identity, array $candidates = []): array
    {
        return compact('product', 'confidence', 'stage', 'identity', 'candidates');
    }

    private function emptyResult(string $stage = 'none', array $candidates = []): array
    {
        return ['product' => null, 'confidence' => 0.0, 'stage' => $stage, 'candidates' => $candidates, 'identity' => null];
    }
}
