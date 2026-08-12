<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Drop if exists (handles case where table was created but migration failed)
        Schema::dropIfExists('missing_item_reports');

        Schema::create('missing_item_reports', function (Blueprint $table) {
            $table->id();

            $table->foreignId('pallet_id')
                ->constrained('pallets')
                ->onDelete('cascade');

            $table->foreignId('inventory_item_id')
                ->constrained('products')
                ->onDelete('cascade');

            // What was expected vs. what actually arrived
            $table->unsignedInteger('expected_quantity');
            $table->decimal('unit_cost', 8, 2)->nullable();
            $table->decimal('total_value', 10, 2)->nullable();

            // Notes about why missing (damaged, shortage, etc.)
            $table->text('notes')->nullable();

            // Track who reported this
            $table->foreignId('reported_by')
                ->nullable()
                ->constrained('users')
                ->onDelete('set null');

            $table->timestamps();
            $table->softDeletes();

            // Indexes for efficient queries
            $table->index('pallet_id');
            $table->index('inventory_item_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('missing_item_reports');
    }
};
