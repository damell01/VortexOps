<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('streamer_log_entries', function (Blueprint $table) {
            $table->unsignedBigInteger('inventory_item_id')->nullable();
            $table->unsignedBigInteger('inventory_location_id')->nullable();
            $table->decimal('inventory_quantity', 8, 2)->nullable();

            $table->foreign('inventory_item_id')
                ->references('id')
                ->on('products')
                ->onDelete('set null');

            $table->foreign('inventory_location_id')
                ->references('id')
                ->on('inventory_locations')
                ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('streamer_log_entries', function (Blueprint $table) {
            $table->dropForeignKeyIfExists(['inventory_item_id']);
            $table->dropForeignKeyIfExists(['inventory_location_id']);
            $table->dropColumn(['inventory_item_id', 'inventory_location_id', 'inventory_quantity']);
        });
    }
};
