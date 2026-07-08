<?php

namespace App\Jobs;

use App\Models\Product;
use App\Services\EmbeddingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;

class GenerateProductEmbeddingJob implements ShouldQueue
{
    use Queueable, InteractsWithQueue;

    public int $timeout = 60;
    public int $tries   = 2;

    public function __construct(private readonly int $productId) {}

    public function handle(EmbeddingService $embedding): void
    {
        $product = Product::find($this->productId);

        if (! $product) {
            return;
        }

        // Skip if already has embedding and job was not forced
        if ($product->embedding !== null) {
            return;
        }

        $text = trim(implode(' ', array_filter([
            $product->name,
            $product->brand,
            $product->sport,
            $product->year,
            $product->set_name,
            $product->product_type,
            $product->configuration,
        ])));

        if (! $text) {
            return;
        }

        $emb = $embedding->embed($text);

        if ($emb) {
            // updateQuietly bypasses observers to prevent re-dispatching this job
            $product->updateQuietly(['embedding' => $emb]);
            $embedding->flushCatalogCache();
        }
    }
}
