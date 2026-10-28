<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_price_change_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->decimal('old_price', 12, 2)->nullable();
            $table->decimal('new_price', 12, 2)->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['created_at', 'id']);
        });

        Schema::create('inventory_price_acknowledgments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('last_event_id')->constrained('inventory_price_change_events')->cascadeOnDelete();
            $table->timestamp('acknowledged_at');
            $table->timestamps();
            $table->unique('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_price_acknowledgments');
        Schema::dropIfExists('inventory_price_change_events');
    }
};
