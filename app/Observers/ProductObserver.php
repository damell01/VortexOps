<?php

namespace App\Observers;

use App\Jobs\GenerateProductEmbeddingJob;
use App\Models\Product;
use App\Support\AdminModules;

class ProductObserver
{
    /**
     * After any Product save, queue embedding generation if the product has none.
     * Skipped when AI is disabled so no queue work piles up on non-AI installs.
     */
    public function saved(Product $product): void
    {
        if ($product->embedding !== null) {
            return;
        }

        if (! AdminModules::isEnabled('ai')) {
            return;
        }

        GenerateProductEmbeddingJob::dispatch($product->id)->onQueue('ai');
    }
}
