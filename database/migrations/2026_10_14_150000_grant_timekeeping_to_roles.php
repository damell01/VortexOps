<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Everyone clocks in now, so roles with an explicit page allow-list (Roles &
 * Permissions) get Timekeeping added — otherwise it stayed hidden for them.
 */
return new class extends Migration
{
    public function up(): void
    {
        $row = DB::table('settings')->where('key', 'role_visible_nav')->first();
        $map = $row ? (json_decode((string) $row->value, true) ?: []) : [];
        $changed = false;

        foreach (['admin', 'streamer', 'fulfillment', 'fulfillment_admin'] as $role) {
            if (! array_key_exists($role, $map) || ! is_array($map[$role])) {
                continue; // no allow-list: the role already sees every page it can open
            }
            foreach (['App\\Filament\\Pages\\Timekeeping'] as $page) {
                if (! in_array($page, $map[$role], true)) {
                    $map[$role][] = $page;
                    $changed = true;
                }
            }
        }

        if ($changed) {
            DB::table('settings')->where('key', 'role_visible_nav')->update(['value' => json_encode($map), 'updated_at' => now()]);
        }
        cache()->forget('setting:role_visible_nav');
    }

    public function down(): void
    {
    }
};
