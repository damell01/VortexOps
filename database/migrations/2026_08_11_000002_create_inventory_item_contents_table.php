<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Creates the inventory_item_contents table for handling hierarchical
     * item relationships (e.g., case contains 20 boxes, box contains 5 items).
     *
     * This allows:
     * - Scanning a case barcode to see all contents
     * - Scanning individual items to see what case they belong to
     * - Tracking quantities at each level
     */
    public function up(): void
    {
        // Drop if exists (handles case where table was created but migration failed)
        Schema::dropIfExists('inventory_item_contents');

        Schema::create('inventory_item_contents', function (Blueprint $table) {
            $table->id();

            // Parent item (the container: case, box, bundle, etc.)
            $table->foreignId('parent_inventory_item_id')
                ->constrained('inventory_items')
                ->onDelete('cascade');

            // Child item (what's inside: box, individual item, etc.)
            $table->foreignId('child_inventory_item_id')
                ->constrained('inventory_items')
                ->onDelete('cascade');

            // How many children per parent (e.g., 20 boxes per case)
            $table->unsignedInteger('quantity_per_parent')->default(1);

            // Unit type descriptor (case, box, bundle, pallet, pack, etc.)
            $table->string('unit_type')->nullable();

            // Optional: Barcode that scans to represent this container
            // (might be different from the child item barcodes)
            $table->string('barcode')->nullable();

            // Optional: Description for clarity
            $table->text('description')->nullable();

            // Track who created this relationship
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->onDelete('set null');

            $table->timestamps();
            $table->softDeletes();

            // Indexes for efficient scanning
            $table->index('parent_inventory_item_id');
            $table->index('child_inventory_item_id');
            $table->index('barcode');
            $table->unique(['parent_inventory_item_id', 'child_inventory_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_item_contents');
    }
};
