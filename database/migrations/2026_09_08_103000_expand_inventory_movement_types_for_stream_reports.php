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

        DB::statement("ALTER TABLE inventory_movements MODIFY movement_type ENUM(
            'opening',
            'transfer',
            'adjustment',
            'sale_deduction',
            'show_sale',
            'giveaway',
            'promo',
            'show_other',
            'show_reversal',
            'internal_use',
            'loss',
            'return',
            'damaged',
            'breakdown'
        ) NOT NULL");
    }

    public function down(): void
    {
        // Do not narrow the enum on rollback. Once show/giveaway/promo movement
        // history exists, removing valid values would make rollback destructive.
    }
};
