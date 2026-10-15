<?php

use App\Support\BusinessPayRunTypes;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Estill's and VCX's pay run types, ready to pick. */
return new class extends Migration
{
    public function up(): void
    {
        BusinessPayRunTypes::ensure();
    }

    public function down(): void
    {
        DB::table('pay_run_types')->whereIn('name', array_column(BusinessPayRunTypes::PRESETS, 0))
            ->whereNotIn('id', DB::table('weekly_payout_batches')->whereNotNull('pay_run_type_id')->select('pay_run_type_id'))
            ->delete();
    }
};
