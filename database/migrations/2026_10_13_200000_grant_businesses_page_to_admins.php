<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Roles with an explicit page allow-list (Roles & Permissions) only see pages
 * named on it, so the new Businesses page was hidden from admins and
 * fulfillment admins (who share the admin list). Add it to that list.
 */
return new class extends Migration
{
    public function up(): void
    {
        $row = DB::table('settings')->where('key', 'role_visible_nav')->first();
        $map = $row ? (json_decode((string) $row->value, true) ?: []) : [];

        if (! array_key_exists('admin', $map) || ! is_array($map['admin'])) {
            return; // no allow-list: admins already see every page
        }

        $page = 'App\\Filament\\Pages\\Businesses';
        if (! in_array($page, $map['admin'], true)) {
            $map['admin'][] = $page;
            DB::table('settings')->where('key', 'role_visible_nav')->update(['value' => json_encode($map), 'updated_at' => now()]);
        }

        cache()->forget('setting:role_visible_nav');
    }

    public function down(): void
    {
    }
};
