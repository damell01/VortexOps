<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') return;
        // Append the existing stocktake event; do not rewrite movement history.
        DB::statement("ALTER TABLE inventory_movements MODIFY movement_type ENUM(
            'opening','transfer','adjustment','sale_deduction','show_sale',
            'giveaway','promo','show_other','show_reversal','internal_use',
            'loss','return','damaged','breakdown','reconciliation'
        ) NOT NULL");
    }

    public function down(): void
    {
        // Removing a value that may now exist would destroy audit history.
    }
};
