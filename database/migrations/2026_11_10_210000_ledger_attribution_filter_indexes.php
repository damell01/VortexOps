<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The ledger page filters entries by their approved match's category
 * (refunds, shipping charges, chargebacks...) and business. These covering
 * indexes let the database answer "approved + category" or "approved +
 * business" from the index alone. Added only when missing.
 */
return new class extends Migration
{
    private const INDEXES = [
        'ledger_attr_status_cat_entry_idx' => ['status', 'category', 'whatnot_ledger_entry_id'],
        'ledger_attr_status_business_entry_idx' => ['status', 'business_id', 'whatnot_ledger_entry_id'],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('ledger_attributions')) return;
        $existing = collect(Schema::getIndexes('ledger_attributions'))->pluck('name')->all();
        foreach (self::INDEXES as $name => $columns) {
            if (in_array($name, $existing, true)) continue;
            Schema::table('ledger_attributions', fn (Blueprint $t) => $t->index($columns, $name));
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('ledger_attributions')) return;
        $existing = collect(Schema::getIndexes('ledger_attributions'))->pluck('name')->all();
        foreach (array_keys(self::INDEXES) as $name) {
            if (in_array($name, $existing, true)) Schema::table('ledger_attributions', fn (Blueprint $t) => $t->dropIndex($name));
        }
    }
};
