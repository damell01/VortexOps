<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deduction_request_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('deduction_request_id')->constrained('deduction_requests')->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->constrained('inventory_items');
            $table->foreignId('inventory_location_id')->constrained('inventory_locations');
            $table->decimal('quantity_suggested', 10, 2);
            $table->decimal('quantity_approved', 10, 2)->default(0);
            $table->decimal('unit_cost_snapshot', 10, 2)->default(0);
            $table->decimal('line_total', 12, 2)->default(0);
            $table->string('raw_description')->nullable();
            $table->enum('ai_confidence', ['high', 'medium', 'low', 'manual'])->default('manual');
            $table->string('ai_reason')->nullable();
            $table->boolean('ops_overridden')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deduction_request_lines');
    }
};
