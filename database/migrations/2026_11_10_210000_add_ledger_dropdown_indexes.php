<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Support ledger filter dropdown DISTINCT queries that were observed taking
 * seconds on large tables. Validate with EXPLAIN before production rollout.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->addIndex('whatnot_ledger_entries', 'status', 'wnledger_status_filter_idx');
        $this->addIndex('whatnot_ledger_entries', 'transaction_type', 'wnledger_type_filter_idx');
    }

    public function down(): void
    {
        foreach (['wnledger_status_filter_idx', 'wnledger_type_filter_idx'] as $name) {
            if (Schema::hasTable('whatnot_ledger_entries') && $this->hasIndex('whatnot_ledger_entries', $name)) {
                Schema::table('whatnot_ledger_entries', fn (Blueprint $table) => $table->dropIndex($name));
            }
        }
    }

    private function addIndex(string $table, string $column, string $name): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column) || $this->hasIndex($table, $name)) {
            return;
        }

        Schema::table($table, fn (Blueprint $blueprint) => $blueprint->index($column, $name));
    }

    private function hasIndex(string $table, string $name): bool
    {
        foreach (Schema::getIndexes($table) as $index) {
            if (($index['name'] ?? null) === $name) {
                return true;
            }
        }

        return false;
    }
};
