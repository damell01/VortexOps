<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // shows
        Schema::table('shows', function (Blueprint $table) {
            $table->index('whatnot_channel_id');
            $table->index('status');
            $table->index('import_source');
            $table->index('created_by');
        });

        // payouts
        Schema::table('payouts', function (Blueprint $table) {
            $table->index('streamer_id');
            $table->index('weekly_payout_batch_id');
            $table->index('status');
        });

        // deduction_requests
        Schema::table('deduction_requests', function (Blueprint $table) {
            $table->index('show_id');
            $table->index('streamer_id');
            $table->index('status');
        });

        // deduction_request_lines
        Schema::table('deduction_request_lines', function (Blueprint $table) {
            $table->index('deduction_request_id');
        });

        // inventory_movements
        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->index('inventory_item_id');
            $table->index('from_location_id');
            $table->index('to_location_id');
            $table->index('created_by');
        });

        // inventory_locations
        Schema::table('inventory_locations', function (Blueprint $table) {
            $table->index('streamer_id');
            $table->index('status');
        });

        // weekly_payout_batches
        Schema::table('weekly_payout_batches', function (Blueprint $table) {
            $table->index('status');
            $table->index('created_by');
            $table->index('finalized_by');
        });

        // streamer_loans
        Schema::table('streamer_loans', function (Blueprint $table) {
            $table->index('streamer_id');
            $table->index('status');
        });

        // streamers
        Schema::table('streamers', function (Blueprint $table) {
            $table->index('status');
        });

        // inventory_items
        Schema::table('inventory_items', function (Blueprint $table) {
            $table->index('is_active');
        });

        // show_streamer pivot — reverse lookup index
        Schema::table('show_streamer', function (Blueprint $table) {
            $table->index('streamer_id');
        });

        // ai_logs
        Schema::table('ai_logs', function (Blueprint $table) {
            $table->index('user_id');
        });

        // activity_log (Spatie)
        Schema::table('activity_log', function (Blueprint $table) {
            $table->index(['causer_type', 'causer_id']);
        });
    }

    public function down(): void
    {
        Schema::table('shows', fn (Blueprint $t) => $t->dropIndex(['whatnot_channel_id', 'status', 'import_source', 'created_by']));
        Schema::table('payouts', fn (Blueprint $t) => $t->dropIndex(['streamer_id', 'weekly_payout_batch_id', 'status']));
        Schema::table('deduction_requests', fn (Blueprint $t) => $t->dropIndex(['show_id', 'streamer_id', 'status']));
        Schema::table('deduction_request_lines', fn (Blueprint $t) => $t->dropIndex(['deduction_request_id']));
        Schema::table('inventory_movements', fn (Blueprint $t) => $t->dropIndex(['inventory_item_id', 'from_location_id', 'to_location_id', 'created_by']));
        Schema::table('inventory_locations', fn (Blueprint $t) => $t->dropIndex(['streamer_id', 'status']));
        Schema::table('weekly_payout_batches', fn (Blueprint $t) => $t->dropIndex(['status', 'created_by', 'finalized_by']));
        Schema::table('streamer_loans', fn (Blueprint $t) => $t->dropIndex(['streamer_id', 'status']));
        Schema::table('streamers', fn (Blueprint $t) => $t->dropIndex(['status']));
        Schema::table('inventory_items', fn (Blueprint $t) => $t->dropIndex(['is_active']));
        Schema::table('show_streamer', fn (Blueprint $t) => $t->dropIndex(['streamer_id']));
        Schema::table('ai_logs', fn (Blueprint $t) => $t->dropIndex(['user_id']));
        Schema::table('activity_log', fn (Blueprint $t) => $t->dropIndex(['causer_type', 'causer_id']));
    }
};
