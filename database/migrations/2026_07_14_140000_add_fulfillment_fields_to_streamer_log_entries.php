<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('streamer_log_entries', function (Blueprint $table) {
            // Real per-show PWE vs label-only shipment counts, entered by the
            // streamer — replaces the units_sold approximation PayoutService used
            // to fall back on for payout_type=pwe_labels streamers.
            if (! Schema::hasColumn('streamer_log_entries', 'pwe_count')) {
                $table->unsignedInteger('pwe_count')->nullable()->after('number_of_shipments');
            }
            if (! Schema::hasColumn('streamer_log_entries', 'label_count')) {
                $table->unsignedInteger('label_count')->nullable()->after('pwe_count');
            }

            // A second, later sign-off for pwe_labels streamers: fulfillment
            // double-checks the counts above are correct before payout is
            // calculated, after the regular streamer/admin review is done.
            if (! Schema::hasColumn('streamer_log_entries', 'fulfillment_reviewed_by')) {
                $table->foreignId('fulfillment_reviewed_by')->nullable()->after('reviewed_at')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('streamer_log_entries', 'fulfillment_reviewed_at')) {
                $table->timestamp('fulfillment_reviewed_at')->nullable()->after('fulfillment_reviewed_by');
            }
        });
    }

    public function down(): void
    {
        Schema::table('streamer_log_entries', function (Blueprint $table) {
            if (Schema::hasColumn('streamer_log_entries', 'fulfillment_reviewed_at')) {
                $table->dropColumn('fulfillment_reviewed_at');
            }
            if (Schema::hasColumn('streamer_log_entries', 'fulfillment_reviewed_by')) {
                $table->dropForeign(['fulfillment_reviewed_by']);
                $table->dropColumn('fulfillment_reviewed_by');
            }
            foreach (['pwe_count', 'label_count'] as $column) {
                if (Schema::hasColumn('streamer_log_entries', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
