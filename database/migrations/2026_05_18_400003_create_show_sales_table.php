<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('show_sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('whatnot_show_id')->constrained('whatnot_shows')->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->nullable()->constrained('inventory_items')->nullOnDelete();
            $table->foreignId('suggested_inventory_item_id')->nullable()->constrained('inventory_items')->nullOnDelete();
            $table->string('item_name');
            $table->string('sku')->nullable();
            $table->decimal('quantity', 10, 2)->default(1);
            $table->decimal('sale_price', 10, 2)->default(0);
            $table->decimal('unit_cost', 10, 2)->nullable();
            $table->string('buyer_username')->nullable();
            $table->string('buyer_name')->nullable();
            $table->string('order_id')->nullable();
            $table->enum('sale_type', ['break_slot', 'fixed_price', 'auction', 'other'])->default('break_slot');
            $table->dateTime('sold_at')->nullable();
            $table->boolean('ai_matched')->default(false);
            $table->decimal('ai_confidence', 5, 2)->nullable();
            $table->json('raw_data')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('show_sales');
    }
};
