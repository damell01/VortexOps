<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('streamers')) {
            return;
        }

        // Streamers all use the calculations-sheet formula by default. Keep
        // each person's existing percentage/tip values intact; only the old
        // payout-model selector and cadence are normalized. A person with an
        // explicit custom formula remains custom.
        DB::table('streamers')
            ->where(function ($query) {
                $query->where('member_type', 'streamer')
                    ->orWhereNull('member_type');
            })
            ->orderBy('id')
            ->chunkById(200, function ($members): void {
                foreach ($members as $member) {
                    $custom = trim((string) ($member->custom_payout_formula ?? ''));

                    DB::table('streamers')
                        ->where('id', $member->id)
                        ->update([
                            'payout_type' => $custom !== '' ? 'custom_formula' : 'profit_share',
                            'payout_cadence' => 'weekly',
                        ]);
                }
            });
    }

    public function down(): void
    {
        // Do not attempt to reconstruct legacy payout types/cadences. The
        // migration intentionally leaves percentages and custom formulas intact.
    }
};
