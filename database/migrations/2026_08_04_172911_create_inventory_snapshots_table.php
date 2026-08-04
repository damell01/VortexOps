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
        Schema::create('inventory_snapshots', function (Blueprint $table) {
            $table->id();
            $table->timestamp('snapshot_date')->index();
            $table->decimal('total_value', 15, 2)->default(0);
            $table->unsignedInteger('total_items')->default(0);
            $table->unsignedInteger('total_quantity')->default(0);
            $table->decimal('average_margin', 5, 2)->nullable();
            $table->json('location_breakdown')->nullable(); // {location_id: {name, value, quantity}}
            $table->json('item_type_breakdown')->nullable(); // {type: {name, value, quantity}}
            $table->json('streamer_breakdown')->nullable(); // {streamer_id: {name, value, quantity}}
            $table->json('slow_moving_items')->nullable(); // items with no movement
            $table->json('stock_outs')->nullable(); // items at 0 quantity
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_snapshots');
    }
};
