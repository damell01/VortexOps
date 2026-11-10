<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Reporting filters use dates, show/streamer pivots and payouts. */
return new class extends Migration {
    private array $indexes = [
        'shows' => [
            'vx_shows_date_id_idx' => ['show_date', 'id'],
            'vx_shows_channel_date_idx' => ['whatnot_channel_id', 'show_date'],
        ],
        'show_streamer' => [
            'vx_show_streamer_stream_show_idx' => ['streamer_id', 'show_id'],
            'vx_show_streamer_show_stream_idx' => ['show_id', 'streamer_id'],
        ],
        'orders' => ['vx_orders_show_cost_idx' => ['show_id', 'total_cost']],
        'payouts' => [
            'vx_payouts_stream_show_idx' => ['streamer_id', 'show_id'],
            'vx_payouts_show_status_idx' => ['show_id', 'status'],
        ],
        'streamer_log_entries' => [
            'vx_stream_logs_show_stream_idx' => ['show_id', 'streamer_id'],
        ],
    ];

    public function up(): void
    {
        foreach ($this->indexes as $table => $indexes) {
            if (! Schema::hasTable($table)) continue;
            foreach ($indexes as $name => $columns) {
                if (collect($columns)->contains(fn ($column) => ! Schema::hasColumn($table, $column))) continue;
                if (collect(Schema::getIndexes($table))->contains(fn ($index) => array_slice($index['columns'] ?? [], 0, count($columns)) === $columns)) continue;
                Schema::table($table, fn (Blueprint $blueprint) => $blueprint->index($columns, $name));
            }
        }
    }

    public function down(): void
    {
        foreach ($this->indexes as $table => $indexes) {
            if (! Schema::hasTable($table)) continue;
            $names = array_column(Schema::getIndexes($table), 'name');
            foreach ($indexes as $name => $columns) {
                if (in_array($name, $names, true)) Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropIndex($name));
            }
        }
    }
};
