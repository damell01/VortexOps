<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deduction_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('whatnot_show_id')->constrained('whatnot_shows')->cascadeOnDelete();
            $table->foreignId('show_sale_id')->nullable()->constrained('show_sales')->nullOnDelete();
            $table->foreignId('inventory_item_id')->constrained('inventory_items')->cascadeOnDelete();
            $table->foreignId('inventory_location_id')->constrained('inventory_locations')->cascadeOnDelete();
            $table->decimal('quantity', 10, 2);
            $table->decimal('unit_cost', 10, 2)->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected', 'executed'])->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('reviewed_at')->nullable();
            $table->foreignId('inventory_movement_id')->nullable()->constrained('inventory_movements')->nullOnDelete();
            $table->text('rejection_reason')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deduction_requests');
    }
};
