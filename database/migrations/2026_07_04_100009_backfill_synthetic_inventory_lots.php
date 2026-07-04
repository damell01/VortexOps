<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Generate one synthetic InventoryLot per existing InventoryStock record so
 * FIFO/LIFO deduction policies have a cost basis for pre-migration stock.
 *
 * Each lot is stamped source=synthetic with unit_cost from the product's
 * current average_cost. remaining_quantity mirrors the stock quantity.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now()->toDateTimeString();

        $stocks = DB::table('inventory_stock')
            ->select('inventory_stock.id', 'inventory_stock.inventory_item_id', 'inventory_stock.quantity')
            ->join('products', 'products.id', '=', 'inventory_stock.inventory_item_id')
            ->where('inventory_stock.quantity', '>', 0)
            ->get();

        foreach ($stocks as $stock) {
            $product = DB::table('products')->where('id', $stock->inventory_item_id)->first();
            if (! $product) {
                continue;
            }

            $unitCost = max((float) $product->average_cost, (float) $product->unit_cost, 0);

            DB::table('inventory_lots')->insert([
                'product_id'          => $stock->inventory_item_id,
                'pallet_line_id'      => null,
                'receiving_session_id' => null,
                'received_by'         => null,
                'quantity'            => $stock->quantity,
                'unit_cost'           => $unitCost,
                'remaining_quantity'  => $stock->quantity,
                'source'              => 'synthetic',
                'status'              => 'active',
                'received_at'         => $now,
                'expires_at'          => null,
                'created_at'          => $now,
                'updated_at'          => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('inventory_lots')->where('source', 'synthetic')->delete();
    }
};
