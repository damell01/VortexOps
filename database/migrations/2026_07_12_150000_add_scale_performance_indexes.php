<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Targeted indexes for the queries that get expensive once tables hold
 * thousands of rows. Best-effort: skip any index that already exists on a
 * given database so re-running never aborts.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Streamer Log filter tabs (To Review / Reviewed / Approved) filter on status.
        $this->addIndex('streamer_log_entries', ['status'], 'sle_status_idx');

        // Product Insights aggregates orders per product and takes MAX(show_date)
        // for "last sold". A composite (item, date) covers both the grouping and
        // the max in one seek instead of scanning every order per product.
        $this->addIndex('whatnot_show_orders', ['inventory_item_id', 'show_date'], 'wnorders_item_date_idx');

        // Filtering the product catalogue by its preferred vendor.
        $this->addIndex('products', ['preferred_vendor_id'], 'products_pref_vendor_idx');

        // Weekly ledger / dashboard sums scan ledger by month; date + type helps.
        if (Schema::hasColumn('whatnot_ledger_entries', 'created_date')) {
            $this->addIndex('whatnot_ledger_entries', ['created_date', 'transaction_type'], 'wnledger_date_type_idx');
        }
    }

    public function down(): void
    {
        $this->dropIndex('streamer_log_entries', 'sle_status_idx');
        $this->dropIndex('whatnot_show_orders', 'wnorders_item_date_idx');
        $this->dropIndex('products', 'products_pref_vendor_idx');
        $this->dropIndex('whatnot_ledger_entries', 'wnledger_date_type_idx');
    }

    private function addIndex(string $table, array $columns, string $name): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }
        foreach ($columns as $c) {
            if (! Schema::hasColumn($table, $c)) {
                return;
            }
        }
        try {
            Schema::table($table, fn (Blueprint $t) => $t->index($columns, $name));
        } catch (\Throwable) {
            // Already present on this database — fine.
        }
    }

    private function dropIndex(string $table, string $name): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }
        try {
            Schema::table($table, fn (Blueprint $t) => $t->dropIndex($name));
        } catch (\Throwable) {
            // Not present — fine.
        }
    }
};
