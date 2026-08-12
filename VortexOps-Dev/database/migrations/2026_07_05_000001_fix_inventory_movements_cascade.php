<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        try { Schema::table('inventory_movements', fn (Blueprint $t) => $t->dropForeign(['inventory_item_id'])); } catch (\Throwable) {}
        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->foreign('inventory_item_id')
                ->references('id')->on('products')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        try { Schema::table('inventory_movements', fn (Blueprint $t) => $t->dropForeign(['inventory_item_id'])); } catch (\Throwable) {}
        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->foreign('inventory_item_id')
                ->references('id')->on('inventory_items')
                ->cascadeOnDelete();
        });
    }
};
