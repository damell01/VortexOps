<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Shows streamed before Nov 17, 2026 were shipped before the Fulfillment
 * Center was in use, so they shouldn't fill Ready to pack. Managers can move
 * the date from the queue on Fulfillment Home. Only sets it when unset, and
 * only in Production: Dev's demo shows are dated in the past on purpose.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! app()->environment('production') || ! Schema::hasTable('settings') || DB::table('settings')->where('key', 'fulfillment_pack_from')->exists()) return;
        DB::table('settings')->insert(['key' => 'fulfillment_pack_from', 'value' => '2026-11-17', 'created_at' => now(), 'updated_at' => now()]);
    }

    public function down(): void
    {
        DB::table('settings')->where('key', 'fulfillment_pack_from')->where('value', '2026-11-17')->delete();
    }
};
