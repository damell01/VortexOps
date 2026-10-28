<?php

namespace App\Observers;

use App\Jobs\GenerateProductEmbeddingJob;
use App\Models\Product;
use App\Support\AdminModules;

class ProductObserver
{
    /**
     * Record selling-price changes independently of the AI embedding feature.
     * Existing catalog items do not generate an alert on initial creation.
     */
    public function updated(Product $product): void
    {
        if (! $product->wasChanged('sale_price')) {
            return;
        }

        $previous = $product->getOriginal('sale_price');
        $new = $product->sale_price;
        // Eloquent's original value is the pre-save value for a change event.
        // getPrevious() is available in recent Laravel versions.
        $before = $product->getPrevious()['sale_price'] ?? $previous;

        if ($before !== null && $new !== null && bccomp((string) $before, (string) $new, 2) === 0) {
            return;
        }

        \Illuminate\Support\Facades\DB::table('inventory_price_change_events')->insert([
            'product_id' => $product->id,
            'old_price' => $before,
            'new_price' => $new,
            'changed_by' => auth()->id(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * After any Product save, queue embedding generation if the product has none.
     * Skipped when AI is disabled so no queue work piles up on non-AI installs.
     */
    public function saved(Product $product): void
    {
        // Cheap memoized check first, so non-AI installs never touch the DB.
        if (! AdminModules::isEnabled('ai')) {
            return;
        }

        if ($product->embedding()->exists()) {
            return;
        }

        GenerateProductEmbeddingJob::dispatch($product->id)->onQueue('ai');
    }
}
