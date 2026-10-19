<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') return;
        // Net units may be negative after refunds. BIGINT also preserves the
        // complete range of values from the former unsigned INT column.
        DB::statement('ALTER TABLE shows MODIFY units_sold BIGINT NOT NULL DEFAULT 0');
    }

    public function down(): void
    {
        // Do not discard negative imported net counts when rolling back code.
    }
};
