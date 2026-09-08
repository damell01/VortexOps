<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('streamers') && Schema::hasColumn('streamers', 'payout_cadence')) {
            DB::table('streamers')
                ->where('payout_cadence', '!=', 'weekly')
                ->orWhereNull('payout_cadence')
                ->update(['payout_cadence' => 'weekly']);
        }
    }

    public function down(): void
    {
        // Intentionally non-destructive. Previous per-person cadence values cannot
        // be reconstructed safely after payroll has been standardized to weekly.
    }
};
