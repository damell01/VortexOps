<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The streamer role was created as "Streamer" (capital S) instead of the
 * lowercase "streamer" every hasRole('streamer')/isStreamer() check in the
 * app actually looks for. Spatie role matching is exact-string and
 * case-sensitive, so every streamer account has been silently failing every
 * row-scoping check (Shows, Payouts, Streamer Log, Streamer Statement,
 * Deduction Requests, Inventory Locations, Timekeeping, ...) this whole
 * time — not a code bug, a data bug. Renaming the role row fixes it
 * instantly since role checks read the name at request time.
 *
 * Also migrates the "Streamer" key in the role_hidden_nav / role_readonly_nav
 * settings (Roles & Permissions page access) to "streamer" so existing
 * configuration carries over instead of silently resetting.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Case-sensitive comparison for role name
        $role = DB::table('roles')->where('name', 'Streamer')->first();

        if (! $role) {
            return; // already fixed, or was never miscased on this environment
        }

        // Don't clobber a genuine pre-existing lowercase "streamer" role —
        // bail loudly rather than silently merging/losing data.
        $existingLowercase = DB::table('roles')
            ->where('name', 'streamer')
            ->where('guard_name', $role->guard_name)
            ->exists();

        if ($existingLowercase) {
            throw new \RuntimeException(
                'Both "Streamer" and "streamer" roles exist — refusing to auto-rename. Resolve manually: decide which one real streamer accounts are assigned to, move any users/permissions off the other, then delete it.'
            );
        }

        DB::table('roles')->where('id', $role->id)->update(['name' => 'streamer']);

        foreach (['role_hidden_nav', 'role_readonly_nav'] as $key) {
            $raw = DB::table('settings')->where('key', $key)->value('value');
            if (! $raw) {
                continue;
            }

            $map = json_decode($raw, true);
            if (! is_array($map) || ! array_key_exists('Streamer', $map)) {
                continue;
            }

            // A lowercase "streamer" entry shouldn't exist yet (no lowercase
            // role existed to write one), but merge defensively just in case.
            $map['streamer'] = array_values(array_unique(array_merge(
                $map['streamer'] ?? [],
                $map['Streamer']
            )));
            unset($map['Streamer']);

            DB::table('settings')->updateOrInsert(['key' => $key], ['value' => json_encode($map)]);
        }

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Not reversible in a meaningful way — renaming back to the broken
        // casing would just reintroduce the bug. No-op.
    }
};
