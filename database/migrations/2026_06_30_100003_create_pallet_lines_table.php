<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pallet_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pallet_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('line_number')->default(1);
            $table->string('description');
            $table->foreignId('inventory_item_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('case_count')->default(1);
            $table->decimal('quantity_per_case', 10, 2)->default(1);
            $table->decimal('unit_cost', 10, 4)->default(0);
            $table->foreignId('inventory_location_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index('pallet_id');
            $table->index('inventory_item_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pallet_lines');
    }
};
