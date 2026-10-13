<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * payout_type was a MySQL ENUM that never learned 'sales_split', so saving an
 * Estill Distribution payout (or a streamer set to that pay type) failed in
 * production. A string holds any type the pay engine knows; the app
 * validates the value. SQLite already stores these as text.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE streamers MODIFY payout_type VARCHAR(30) NOT NULL DEFAULT 'profit_share'");
        DB::statement('ALTER TABLE payouts MODIFY payout_type VARCHAR(30) NOT NULL');
    }

    public function down(): void
    {
        // Narrowing back to an ENUM would fail on any sales_split row.
    }
};
