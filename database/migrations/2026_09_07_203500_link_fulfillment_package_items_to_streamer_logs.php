<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fulfillment_package_items', function (Blueprint $table) {
            if (! Schema::hasColumn('fulfillment_package_items', 'streamer_log_item_id')) {
                $table->foreignId('streamer_log_item_id')
                    ->nullable()
                    ->after('product_id')
                    ->constrained('streamer_log_items')
                    ->cascadeOnDelete();
            }

            if (! Schema::hasColumn('fulfillment_package_items', 'packed_by')) {
                $table->foreignId('packed_by')->nullable()->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('fulfillment_package_items', 'packed_at')) {
                $table->timestamp('packed_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('fulfillment_package_items', function (Blueprint $table) {
            if (Schema::hasColumn('fulfillment_package_items', 'packed_at')) {
                $table->dropColumn('packed_at');
            }
            if (Schema::hasColumn('fulfillment_package_items', 'packed_by')) {
                $table->dropConstrainedForeignId('packed_by');
            }
            if (Schema::hasColumn('fulfillment_package_items', 'streamer_log_item_id')) {
                $table->dropConstrainedForeignId('streamer_log_item_id');
            }
        });
    }
};
