<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement(
            "ALTER TABLE streamers MODIFY payout_type
             ENUM('profit_share','package','hourly','flat_rate','pwe_labels','hybrid','custom_formula')
             NOT NULL DEFAULT 'profit_share'"
        );

        DB::statement(
            "ALTER TABLE payouts MODIFY payout_type
             ENUM('profit_share','package','hourly','flat_rate','custom_formula','pwe_labels','hybrid')
             NOT NULL"
        );
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement(
            "ALTER TABLE streamers MODIFY payout_type
             ENUM('profit_share','package','hourly','flat_rate')
             NOT NULL DEFAULT 'profit_share'"
        );

        DB::statement(
            "ALTER TABLE payouts MODIFY payout_type
             ENUM('profit_share','package','hourly','flat_rate','custom_formula')
             NOT NULL"
        );
    }
};
