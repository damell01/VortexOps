<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Products with no stock line anywhere belong to Main: give each a stock line
 * (quantity 0) at the main location, so they list under it and can be counted
 * there. Products already stocked somewhere are left alone. Idempotent.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('products') || ! Schema::hasTable('inventory_stock') || ! Schema::hasTable('inventory_locations')) {
            return;
        }

        $main = DB::table('inventory_locations')->whereNull('streamer_id')->where('status', 'active')->where('type', 'main_storage')
            ->orderByRaw("CASE WHEN LOWER(name) = 'main warehouse' THEN 0 ELSE 1 END")->orderBy('id')->value('id');
        if (! $main) {
            return;
        }

        $now = now();
        DB::table('products')
            ->whereNull('deleted_at')
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('inventory_stock')->whereColumn('inventory_stock.inventory_item_id', 'products.id'))
            ->orderBy('id')
            ->pluck('id')
            ->chunk(500)
            ->each(fn ($ids) => DB::table('inventory_stock')->insertOrIgnore($ids->map(fn ($id) => [
                'inventory_item_id' => $id, 'inventory_location_id' => $main, 'quantity' => 0,
                'created_at' => $now, 'updated_at' => $now,
            ])->all()));
    }

    public function down(): void
    {
        // Zero-quantity lines are harmless; nothing to undo.
    }
};
