<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('streamers', 'payout_cadence')) {
            Schema::table('streamers', function (Blueprint $table) {
                // Regular payout types (hourly, package, flat_rate, pwe_labels) run
                // weekly; profit share and tips are batched monthly instead.
                // Editable per-streamer — this is only a sensible starting default.
                $table->string('payout_cadence')->default('weekly')->after('payout_type');
            });

            // Backfill: streamers whose earnings are profit-share or tips-based
            // default to monthly; everyone else stays weekly.
            DB::table('streamers')
                ->where(function ($q) {
                    $q->whereIn('payout_type', ['profit_share', 'hybrid'])
                        ->orWhere('include_tips', true);
                })
                ->update(['payout_cadence' => 'monthly']);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('streamers', 'payout_cadence')) {
            Schema::table('streamers', function (Blueprint $table) {
                $table->dropColumn('payout_cadence');
            });
        }
    }
};
