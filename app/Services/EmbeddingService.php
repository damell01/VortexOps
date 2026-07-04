<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Wraps the Ollama /api/embeddings endpoint.
 * Runs entirely locally — no external API calls.
 */
class EmbeddingService
{
    private string $baseUrl;
    private string $model;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.ollama.url', 'http://localhost:11434'), '/');
        $this->model   = config('services.ollama.embedding_model', 'nomic-embed-text');
    }

    /**
     * Generate an embedding vector for the given text.
     * Returns null if Ollama is unavailable.
     *
     * @return float[]|null
     */
    public function embed(string $text): ?array
    {
        $text = $this->normalizeText($text);

        try {
            $response = Http::timeout(10)->post("{$this->baseUrl}/api/embeddings", [
                'model'  => $this->model,
                'prompt' => $text,
            ]);

            if ($response->successful()) {
                return $response->json('embedding');
            }
        } catch (\Throwable $e) {
            Log::warning("EmbeddingService: Ollama unavailable — {$e->getMessage()}");
        }

        return null;
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
     * Find the product IDs with the highest cosine similarity to the query,
     * using pre-loaded embeddings from the cache.
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
            return \App\Models\Product::whereNotNull('embedding')
                ->where('is_active', true)
                ->pluck('embedding', 'id')
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
        try {
            $response = Http::timeout(3)->get("{$this->baseUrl}/api/tags");
            return $response->successful();
        } catch (\Throwable) {
            return false;
        }
    }
}
