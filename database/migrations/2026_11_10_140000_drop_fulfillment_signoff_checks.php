<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sign-off became a single confirm after reviewing the show: no typed
 * signature and no per-package check-off. Who signed off and when stay on
 * the run (approved_by / approved_at).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('fulfillment_runs', 'signoff_signature')) {
            Schema::table('fulfillment_runs', fn (Blueprint $table) => $table->dropColumn('signoff_signature'));
        }
        foreach (['fulfillment_run_packages', 'fulfillment_run_buyers'] as $name) {
            foreach (['checked_at', 'checked_by'] as $column) {
                if (Schema::hasColumn($name, $column)) {
                    Schema::table($name, fn (Blueprint $table) => $table->dropColumn($column));
                }
            }
        }
    }

    public function down(): void
    {
        // Nothing to restore: the columns held no data anyone relies on.
    }
};
