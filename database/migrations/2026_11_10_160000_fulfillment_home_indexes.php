<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Indexes for what Fulfillment Home asks on every refresh: open runs in
 * queue order, packages and buyers packed today, and a show's shipments by
 * status. Each is added only when missing, so the migration is safe to rerun.
 */
return new class extends Migration
{
    private const INDEXES = [
        'fulfillment_runs' => ['fr_status_ready_idx' => ['status', 'ready_at']],
        'fulfillment_run_packages' => ['frp_status_packed_idx' => ['status', 'packed_at']],
        'fulfillment_run_buyers' => ['frb_status_packed_idx' => ['status', 'packed_at'], 'frb_run_status_idx' => ['run_id', 'status']],
        'shipments' => ['shipments_show_status_idx' => ['show_id', 'status']],
        'fulfillment_events' => ['fe_created_idx' => ['created_at']],
    ];

    public function up(): void
    {
        foreach (self::INDEXES as $table => $indexes) {
            if (! Schema::hasTable($table)) continue;
            $existing = collect(Schema::getIndexes($table))->pluck('name')->all();
            foreach ($indexes as $name => $columns) {
                if (in_array($name, $existing, true)) continue;
                foreach ($columns as $column) if (! Schema::hasColumn($table, $column)) continue 2;
                Schema::table($table, fn (Blueprint $t) => $t->index($columns, $name));
            }
        }
    }

    public function down(): void
    {
        foreach (self::INDEXES as $table => $indexes) {
            if (! Schema::hasTable($table)) continue;
            $existing = collect(Schema::getIndexes($table))->pluck('name')->all();
            foreach (array_keys($indexes) as $name) {
                if (in_array($name, $existing, true)) Schema::table($table, fn (Blueprint $t) => $t->dropIndex($name));
            }
        }
    }
};
