<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Indexes for queries every page runs (from perf:pages on production):
 *  - the bell's unread count: notifications by person, unread only;
 *  - the dashboard's recent stock movements out of a set of locations.
 * Adding an index does not change any rows.
 */
return new class extends Migration {
    private array $indexes = [
        'notifications' => [
            'vx_notif_unread_idx' => ['notifiable_type', 'notifiable_id', 'read_at'],
        ],
        'inventory_movements' => [
            'vx_move_from_created_idx' => ['from_location_id', 'created_at'],
        ],
    ];

    public function up(): void
    {
        foreach ($this->indexes as $table => $indexes) {
            if (! Schema::hasTable($table)) continue;
            $existing = Schema::getIndexes($table);
            foreach ($indexes as $name => $columns) {
                if (count(array_filter($columns, fn ($c) => Schema::hasColumn($table, $c))) !== count($columns)) continue;
                if (collect($existing)->contains(fn ($i) => ($i['name'] ?? null) === $name || ($i['columns'] ?? []) === $columns)) continue;
                Schema::table($table, fn (Blueprint $t) => $t->index($columns, $name));
            }
        }
    }

    public function down(): void
    {
        foreach ($this->indexes as $table => $indexes) {
            if (! Schema::hasTable($table)) continue;
            $names = array_column(Schema::getIndexes($table), 'name');
            foreach (array_keys($indexes) as $name) {
                if (in_array($name, $names, true)) Schema::table($table, fn (Blueprint $t) => $t->dropIndex($name));
            }
        }
    }
};
