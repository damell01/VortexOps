<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_lots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products');
            $table->foreignId('pallet_line_id')->nullable()->constrained('pallet_lines')->nullOnDelete();
            $table->foreignId('receiving_session_id')->nullable()->constrained('receiving_sessions')->nullOnDelete();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();

            $table->decimal('quantity', 10, 2);             // total qty received in this lot
            $table->decimal('unit_cost', 10, 4)->default(0);
            $table->decimal('remaining_quantity', 10, 2);   // decremented as stock is deducted

            // Traceability
            $table->string('supplier_invoice')->nullable();
            $table->string('purchase_order')->nullable();
            $table->string('vendor_sku')->nullable();
            $table->string('manufacturer_sku')->nullable();

            // source: received | synthetic | adjustment
            $table->string('source', 20)->default('received');

            // status: active | depleted | quarantined
            $table->string('status', 20)->default('active');

            $table->timestamp('received_at')->nullable();
            $table->timestamp('expires_at')->nullable();    // for date-sensitive product types

            $table->timestamps();

            $table->index(['product_id', 'status']);
            $table->index('pallet_line_id');
            $table->index('receiving_session_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_lots');
    }
};
