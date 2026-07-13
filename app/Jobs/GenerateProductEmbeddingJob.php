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
        if ($product->embedding()->exists()) {
            return;
        }

        if ($embedding->embedProduct($product)) {
            $embedding->flushCatalogCache();
        }
    }
}
