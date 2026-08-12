<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatnot_show_orders', function (Blueprint $table) {
            if (! Schema::hasColumn('whatnot_show_orders', 'inventory_location_id')) {
                $table->unsignedBigInteger('inventory_location_id')->nullable()->after('inventory_item_id');
            }
            // Streamer-entered cost (what the item cost them), distinct from the
            // Whatnot sale price already stored in unit_price/total_price.
            if (! Schema::hasColumn('whatnot_show_orders', 'unit_cost')) {
                $table->decimal('unit_cost', 10, 2)->nullable()->after('total_price');
            }
            if (! Schema::hasColumn('whatnot_show_orders', 'total_cost')) {
                $table->decimal('total_cost', 10, 2)->nullable()->after('unit_cost');
            }
        });

        if (Schema::hasTable('inventory_locations')) {
            try {
                Schema::table('whatnot_show_orders', function (Blueprint $table) {
                    $table->foreign('inventory_location_id')->references('id')->on('inventory_locations')->nullOnDelete();
                });
            } catch (\Throwable $e) {
                // best-effort — skip if it can't be formed
            }
        }
    }

    public function down(): void
    {
        Schema::table('whatnot_show_orders', function (Blueprint $table) {
            try { $table->dropForeign(['inventory_location_id']); } catch (\Throwable $e) {}
            foreach (['inventory_location_id', 'unit_cost', 'total_cost'] as $col) {
                if (Schema::hasColumn('whatnot_show_orders', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
