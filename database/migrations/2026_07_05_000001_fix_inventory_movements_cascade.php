<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->dropForeign(['inventory_item_id']);
            // Re-point FK at the renamed table (inventory_items → products) and use RESTRICT
            // to prevent deleting products that have financial movement history.
            $table->foreign('inventory_item_id')
                ->references('id')->on('products')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->dropForeign(['inventory_item_id']);
            $table->foreign('inventory_item_id')
                ->references('id')->on('inventory_items')
                ->cascadeOnDelete();
        });
    }
};
