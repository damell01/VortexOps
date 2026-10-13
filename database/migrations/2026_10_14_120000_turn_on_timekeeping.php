<?php

use App\Support\AdminModules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Hours Worked now comes from clocking in, so the Timekeeping module is on.
 * Shows from before today are not reviewed against the clock — there was no
 * clock for them.
 */
return new class extends Migration
{
    public function up(): void
    {
        $row = DB::table('settings')->where('key', 'enabled_admin_modules')->first();
        $enabled = $row ? (json_decode((string) $row->value, true) ?: null) : null;
        $enabled = is_array($enabled) ? $enabled : AdminModules::defaultEnabledSlugs();

        if (! in_array('timekeeping', $enabled, true)) {
            $enabled[] = 'timekeeping';
            DB::table('settings')->updateOrInsert(['key' => 'enabled_admin_modules'], ['value' => json_encode(array_values($enabled)), 'updated_at' => now(), 'created_at' => $row->created_at ?? now()]);
        }

        if (! DB::table('settings')->where('key', 'hours_tracking_since')->exists()) {
            DB::table('settings')->insert(['key' => 'hours_tracking_since', 'value' => now('America/Chicago')->toDateString(), 'created_at' => now(), 'updated_at' => now()]);
        }

        cache()->forget('setting:enabled_admin_modules');
        cache()->forget('setting:hours_tracking_since');
    }

    public function down(): void
    {
    }
};
