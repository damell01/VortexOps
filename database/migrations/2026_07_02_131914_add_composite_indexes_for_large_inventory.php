<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // (inventory_item_id, created_at) composite — per-item movement history queries
        // use inventory_item_id as range filter then sort by created_at. Without this,
        // the DB filesorts the matching rows, which is expensive at high movement volume.
        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->index(['inventory_item_id', 'created_at'], 'imovements_item_date_idx');
        });

        // name index on inventory_items speeds up ORDER BY name on large catalogs
        // and LIKE 'prefix%' global search prefix scans.
        Schema::table('inventory_items', function (Blueprint $table) {
            if (! Schema::hasIndex('inventory_items', 'inventory_items_name_index')) {
                $table->index('name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->dropIndex('imovements_item_date_idx');
        });

        Schema::table('inventory_items', function (Blueprint $table) {
            $table->dropIndexIfExists('inventory_items_name_index');
        });
    }
};
