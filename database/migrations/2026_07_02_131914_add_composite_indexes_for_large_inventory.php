<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        try { Schema::table('inventory_movements', fn (Blueprint $t) => $t->dropIndex('imovements_item_date_idx')); } catch (\Throwable) {}
        Schema::table('inventory_movements', fn (Blueprint $t) => $t->index(['inventory_item_id', 'created_at'], 'imovements_item_date_idx'));

        Schema::table('inventory_items', function (Blueprint $table) {
            if (! Schema::hasIndex('inventory_items', 'inventory_items_name_index')) {
                $table->index('name');
            }
        });
    }

    public function down(): void
    {
        try { Schema::table('inventory_movements', fn (Blueprint $t) => $t->dropIndex('imovements_item_date_idx')); } catch (\Throwable) {}

        Schema::table('inventory_items', function (Blueprint $table) {
            $table->dropIndexIfExists('inventory_items_name_index');
        });
    }
};
