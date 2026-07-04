<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('receiving_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->nullable()->constrained('vendors')->nullOnDelete();
            $table->foreignId('received_by')->constrained('users');

            $table->string('invoice_number')->nullable();
            $table->string('purchase_order')->nullable();
            $table->string('uploaded_file_path')->nullable();   // storage path of original file
            $table->string('file_type', 20)->nullable();        // pdf | excel | image | manual

            // Status: pending | parsing | reviewing | completed | cancelled
            $table->string('status', 20)->default('pending');

            // Matching summary (populated after parse/match)
            $table->unsignedSmallInteger('total_lines')->default(0);
            $table->unsignedSmallInteger('auto_matched_count')->default(0);
            $table->unsignedSmallInteger('review_required_count')->default(0);
            $table->unsignedSmallInteger('new_product_count')->default(0);

            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('received_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('receiving_sessions');
    }
};
