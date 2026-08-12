<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('scan_logs')) {
            return;
        }

        Schema::create('scan_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scan_session_id')->constrained('scan_sessions')->cascadeOnDelete();
            $table->string('barcode');
            $table->unsignedBigInteger('inventory_item_id')->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->timestamp('scanned_at')->useCurrent();
            $table->enum('result', ['success', 'not_found', 'duplicate', 'error'])->default('success');
            $table->text('notes')->nullable();

            $table->index(['scan_session_id', 'scanned_at']);
        });

        // Add indices separately to avoid conflicts
        if (!Schema::hasColumn('scan_logs', 'barcode_index')) {
            Schema::table('scan_logs', function (Blueprint $table) {
                try {
                    $table->index('barcode');
                } catch (\Exception $e) {
                    // Index might already exist
                }
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('scan_logs');
    }
};
