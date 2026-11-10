<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Tips approved to a show in the ledger never reached the show's own tips,
 * so pay that includes tips saw $0. Bring each show up to what the ledger has
 * approved for it (never added on top: it is the same money). Pay already on
 * a run is not touched here; recalculating a draft run picks it up.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('ledger_attributions as a')
            ->join('whatnot_ledger_entries as le', 'le.id', '=', 'a.whatnot_ledger_entry_id')
            ->where('a.status', 'approved')->whereNotNull('a.show_id')
            ->where(fn ($q) => $q->where('a.category', 'tips')->orWhereRaw('LOWER(TRIM(le.transaction_type)) IN (?, ?)', ['tip', 'tips']))
            ->groupBy('a.show_id')
            ->selectRaw('a.show_id, SUM(le.amount) as total')
            ->get()
            ->each(function ($row) {
                $total = round((float) $row->total, 2);
                if ($total <= 0) return;
                DB::table('shows')->where('id', $row->show_id)
                    ->where(fn ($q) => $q->whereNull('tips')->orWhere('tips', '<', $total))
                    ->update(['tips' => $total]);
            });
    }

    public function down(): void
    {
        // Data backfill: nothing to undo.
    }
};
