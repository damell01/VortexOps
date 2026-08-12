<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add advanced tracking to pallets table
        Schema::table('pallets', function (Blueprint $table) {
            $table->string('stage')->default('created')->comment('created|staged|receiving|received|processed'); // workflow stage
            $table->text('packing_slip_path')->nullable()->comment('path to uploaded packing slip');
            $table->dateTime('staged_at')->nullable()->comment('when pallet was staged for receiving');
            $table->dateTime('receiving_started_at')->nullable()->comment('when receiving started');
            $table->integer('line_items_total')->default(0)->comment('total expected items across all lines');
            $table->integer('line_items_received')->default(0)->comment('items received so far');
        });

        // Track cost history for pallet lines (vendor/price changes)
        Schema::create('pallet_line_costs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pallet_line_id')->constrained('pallet_lines')->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->nullable()->constrained('products')->nullOnDelete();
            $table->decimal('unit_cost', 10, 2);
            $table->decimal('previous_unit_cost', 10, 2)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Track pallet receiving sessions (for scanner state/progress)
        Schema::create('scanner_receiving_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pallet_id')->constrained('pallets')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('mode')->default('barcode')->comment('barcode|camera|manual');
            $table->integer('items_scanned')->default(0);
            $table->integer('items_added')->default(0);
            $table->dateTime('started_at');
            $table->dateTime('last_activity_at');
            $table->dateTime('ended_at')->nullable();
            $table->timestamps();
        });

        // Track packing slip uploads
        Schema::create('pallet_packing_slips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pallet_id')->constrained('pallets')->cascadeOnDelete();
            $table->string('file_path');
            $table->string('original_filename');
            $table->integer('file_size');
            $table->string('mime_type');
            $table->text('extracted_text')->nullable()->comment('OCR extracted text from image/PDF');
            $table->foreignId('uploaded_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pallet_packing_slips');
        Schema::dropIfExists('scanner_receiving_sessions');
        Schema::dropIfExists('pallet_line_costs');

        Schema::table('pallets', function (Blueprint $table) {
            $table->dropColumn([
                'stage',
                'packing_slip_path',
                'staged_at',
                'receiving_started_at',
                'line_items_total',
                'line_items_received',
            ]);
        });
    }
};
