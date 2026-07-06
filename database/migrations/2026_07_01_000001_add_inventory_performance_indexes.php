<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // inventory_items: category is used heavily in list filters
        try { Schema::table('inventory_items',   fn (Blueprint $t) => $t->dropIndex('iitems_category_idx')); } catch (\Throwable) {}
        Schema::table('inventory_items', fn (Blueprint $t) => $t->index('category', 'iitems_category_idx'));

        // inventory_locations: type is used in activeOptionsByType() called on many forms
        try { Schema::table('inventory_locations', fn (Blueprint $t) => $t->dropIndex('iloc_type_idx')); } catch (\Throwable) {}
        Schema::table('inventory_locations', fn (Blueprint $t) => $t->index('type', 'iloc_type_idx'));

        // inventory_cases: replace separate pallet_line_id index with a composite
        // (pallet_line_id, status) so `WHERE pallet_line_id=? AND status='expected'`
        // uses one index instead of two, cutting receiving query time significantly.
        // Must drop FK before dropping its supporting index on MySQL, then re-add.
        try { Schema::table('inventory_cases', fn (Blueprint $t) => $t->dropForeign(['pallet_line_id'])); } catch (\Throwable) {}
        try { Schema::table('inventory_cases', fn (Blueprint $t) => $t->dropIndex(['pallet_line_id'])); } catch (\Throwable) {}
        try { Schema::table('inventory_cases', fn (Blueprint $t) => $t->dropIndex('icases_line_status_idx')); } catch (\Throwable) {}
        Schema::table('inventory_cases', function (Blueprint $table) {
            $table->index(['pallet_line_id', 'status'], 'icases_line_status_idx');
            $table->foreign('pallet_line_id')->references('id')->on('pallet_lines')->cascadeOnDelete();
        });

        // inventory_movements: reference lookup (audit trail by source record)
        try { Schema::table('inventory_movements', fn (Blueprint $t) => $t->dropIndex('imovements_ref_idx')); } catch (\Throwable) {}
        Schema::table('inventory_movements', fn (Blueprint $t) => $t->index(['reference_type', 'reference_id'], 'imovements_ref_idx'));
    }

    public function down(): void
    {
        Schema::table('inventory_items', function (Blueprint $table) {
            $table->dropIndex('iitems_category_idx');
        });

        Schema::table('inventory_locations', function (Blueprint $table) {
            $table->dropIndex('iloc_type_idx');
        });

        Schema::table('inventory_cases', function (Blueprint $table) {
            $table->dropForeign(['pallet_line_id']);
            $table->dropIndex('icases_line_status_idx');
            $table->index('pallet_line_id');
            $table->foreign('pallet_line_id')->references('id')->on('pallet_lines')->cascadeOnDelete();
        });

        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->dropIndex('imovements_ref_idx');
        });
    }
};
