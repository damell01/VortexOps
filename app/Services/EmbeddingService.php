<?php

namespace App\Services;

use App\Services\AI\OllamaClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Wraps the Ollama /api/embeddings endpoint.
 * Delegates HTTP calls to OllamaClient so all Ollama traffic goes through one abstraction.
 */
class EmbeddingService
{
    public function __construct(
        private readonly OllamaClient $ollama,
    ) {}

    /**
     * Generate an embedding vector for the given text.
     * Returns null if Ollama is unavailable.
     *
     * @return float[]|null
     */
    public function embed(string $text): ?array
    {
        return $this->ollama->embed($this->normalizeText($text));
    }

    /**
     * The descriptive text an embedding is built from for a product — name plus
     * the structured attributes that distinguish it. Single source of truth so
     * the queued job and the synchronous "generate now" path stay in sync.
     */
    public function buildProductText(\App\Models\Product $product): string
    {
        return trim(implode(' ', array_filter([
            $product->name,
            $product->brand,
            $product->sport,
            $product->year,
            $product->set_name,
            $product->product_type,
            $product->configuration,
        ])));
    }

    /**
     * Embed a single product and store the vector. Returns true when the product
     * now has an embedding (either freshly written or already present without
     * --force). Returns false if there's no text to embed or Ollama is
     * unavailable. Caller is responsible for flushing the catalogue cache after
     * a batch.
     */
    public function embedProduct(\App\Models\Product $product, bool $force = false): bool
    {
        if (! $force && $product->embedding()->exists()) {
            return true;
        }

        $text = $this->buildProductText($product);
        if ($text === '') {
            return false;
        }

        $vector = $this->embed($text);
        if (! $vector) {
            return false;
        }

        \App\Models\ProductEmbedding::updateOrCreate(
            ['product_id' => $product->id],
            ['vector' => $vector],
        );

        return true;
    }

    /**
     * Cosine similarity between two embedding vectors. Returns 0.0–1.0.
     *
     * @param float[] $a
     * @param float[] $b
     */
    public function cosineSimilarity(array $a, array $b): float
    {
        if (count($a) !== count($b) || count($a) === 0) {
            return 0.0;
        }

        $dot   = 0.0;
        $normA = 0.0;
        $normB = 0.0;

        foreach ($a as $i => $val) {
            $dot   += $val * $b[$i];
            $normA += $val * $val;
            $normB += $b[$i] * $b[$i];
        }

        $denom = sqrt($normA) * sqrt($normB);
        return $denom > 0 ? $dot / $denom : 0.0;
    }

    /**
     * Find the product IDs with the highest cosine similarity to the query.
     *
     * @param  float[]             $queryEmbedding
     * @param  array<int, float[]> $catalog   product_id → embedding
     * @return array<int, float>              product_id → similarity, sorted desc
     */
    public function rankBySimilarity(array $queryEmbedding, array $catalog, int $topN = 5): array
    {
        $scores = [];
        foreach ($catalog as $productId => $embedding) {
            if (! empty($embedding)) {
                $scores[$productId] = $this->cosineSimilarity($queryEmbedding, $embedding);
            }
        }

        arsort($scores);
        return array_slice($scores, 0, $topN, true);
    }

    /**
     * Return a map of product_id → embedding for all products that have one,
     * cached for 10 minutes so repeated matching calls don't hit the DB each time.
     *
     * @return array<int, float[]>
     */
    public function productEmbeddingCatalog(): array
    {
        return Cache::remember('embedding:product_catalog', 600, function () {
            return \App\Models\ProductEmbedding::query()
                ->whereHas('product', fn ($q) => $q->where('is_active', true))
                ->pluck('vector', 'product_id')
                ->toArray();
        });
    }

    public function flushCatalogCache(): void
    {
        Cache::forget('embedding:product_catalog');
    }

    /**
     * Normalize text for embedding: lowercase, collapse whitespace, remove punctuation.
     */
    public function normalizeText(string $text): string
    {
        $text = strtolower(trim($text));
        $text = preg_replace('/[^\w\s]/', ' ', $text);
        $text = preg_replace('/\s+/', ' ', $text);
        return trim($text);
    }

    public function isAvailable(): bool
    {
        return $this->ollama->isOnline();
    }
}
