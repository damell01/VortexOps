<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Repoint whatnot_show_orders.inventory_item_id at the products table.
 *
 * The inventory_items table was renamed to products (2026_07_04). Two problems
 * fell out of that:
 *   1. Databases created before the rename kept a foreign key pointing at the
 *      now-missing inventory_items table, so INSERTs into whatnot_show_orders
 *      failed with "no such table: inventory_items" once foreign key checks
 *      were on.
 *   2. On a fresh migrate the extend_whatnot_show_orders migration (which runs
 *      after the rename) guards its FK with hasTable('inventory_items') — false
 *      post-rename — so no FK was ever created and inventory_item_id had no
 *      referential integrity at all.
 *
 * This drops any stale FK and adds the correct one referencing products. It is
 * best-effort: the Eloquent relation works without a DB-level constraint, so a
 * database that can't form it (or already has it) must never abort the run.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('whatnot_show_orders', 'inventory_item_id')) {
            return;
        }

        // Remove the stale FK (references inventory_items) if it's still there.
        try {
            Schema::table('whatnot_show_orders', function (Blueprint $table) {
                $table->dropForeign(['inventory_item_id']);
            });
        } catch (\Throwable $e) {
            // No FK to drop (fresh install) or the platform can't express it — fine.
        }

        if (! Schema::hasTable('products')) {
            return;
        }

        try {
            Schema::table('whatnot_show_orders', function (Blueprint $table) {
                $table->foreign('inventory_item_id')
                    ->references('id')->on('products')
                    ->nullOnDelete();
            });
        } catch (\Throwable $e) {
            // Constraint already present or can't be formed here — skip.
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('whatnot_show_orders', 'inventory_item_id')) {
            return;
        }

        try {
            Schema::table('whatnot_show_orders', function (Blueprint $table) {
                $table->dropForeign(['inventory_item_id']);
            });
        } catch (\Throwable $e) {
            // Nothing to drop — fine.
        }
    }
};
