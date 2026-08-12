<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Add fields to pallets table for enhanced receiving workflow:
     * - Digital signature capture
     * - Receiver name tracking
     * - Media attachment counting (denormalized for UI performance)
     */
    public function up(): void
    {
        Schema::table('pallets', function (Blueprint $table) {
            // Digital signature path and timestamp
            $table->string('signature_path')->nullable()->after('notes');
            $table->timestamp('signature_timestamp')->nullable()->after('signature_path');

            // Name of person who signed (useful for records)
            $table->string('received_by_name')->nullable()->after('signature_timestamp');

            // Denormalized count for quick UI rendering
            $table->unsignedInteger('attachments_count')->default(0)->after('received_by_name');

            // Index for queries
            $table->index('signature_timestamp');
        });
    }

    public function down(): void
    {
        Schema::table('pallets', function (Blueprint $table) {
            $table->dropColumn([
                'signature_path',
                'signature_timestamp',
                'received_by_name',
                'attachments_count',
            ]);
        });
    }
};
