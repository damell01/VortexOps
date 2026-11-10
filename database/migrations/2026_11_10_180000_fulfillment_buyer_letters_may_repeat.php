<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Buyer letters are now the buyer's username initial (the camera label), so
 * two buyers on a show can share one. The one-letter-per-run rule would stop
 * packing from starting on any show where two buyer names start alike; the
 * buyer's username stays unique per run instead.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fulfillment_run_buyers')) return;
        $indexes = collect(Schema::getIndexes('fulfillment_run_buyers'))->keyBy('name');
        if ($indexes->has('fulfillment_run_buyers_run_id_letter_unique')) {
            Schema::table('fulfillment_run_buyers', function (Blueprint $table) {
                // Keep run_id indexed for its foreign key while the unique index goes.
                $table->index(['run_id', 'letter'], 'frb_run_letter_idx');
            });
            Schema::table('fulfillment_run_buyers', fn (Blueprint $table) => $table->dropUnique('fulfillment_run_buyers_run_id_letter_unique'));
        }
    }

    public function down(): void
    {
        // Letters can now repeat; restoring the unique index would fail on real data.
    }
};
