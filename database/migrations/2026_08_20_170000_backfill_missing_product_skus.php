<?php

use App\Models\Product;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Give a SKU to the products that never got one.
 *
 * The create form generated one, so anything typed in by hand has a SKU and
 * everything else does not — products created by scanning an unknown barcode at
 * a pallet, or through quick add, arrived with the column blank. The model now
 * fills it in on the way in, but that only helps the ones created from here on.
 *
 * Done through the model rather than in SQL so each one goes through the same
 * uniqueness check as a new product, and touching nothing that already has one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Product::withTrashed()
            ->where(fn ($q) => $q->whereNull('sku')->orWhere('sku', ''))
            ->orderBy('id')
            ->chunkById(200, function ($products) {
                foreach ($products as $product) {
                    // No touch(): a backfill is not an edit anyone made, and
                    // moving updated_at would misdate every one of them.
                    DB::table('products')
                        ->where('id', $product->id)
                        ->update(['sku' => Product::generateSku()]);
                }
            });
    }

    public function down(): void
    {
        // Nothing to undo. Removing the SKUs would break the scans that now
        // resolve through them, and there is no record of which were backfilled.
    }
};
