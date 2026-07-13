<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;

/**
 * Six menu items (Streamer Log, Ledger, Shipping Surcharge, Profit Share
 * Packet, Streamer Analytics, Streamer Statements) moved from the standalone
 * feature-flag system into the "Workspace Modules & Features" list so they're
 * all toggled in one place.
 *
 * Feature enablement is opt-in: once a workspace has saved an explicit feature
 * list, only listed slugs are on. So on an existing install these newly-listed
 * slugs would default to OFF and the items would vanish. Append them to any
 * saved list to preserve exactly what's visible today. Installs that never
 * saved a list (the setting is unset) already default to "all features on" and
 * need no change.
 */
return new class extends Migration
{
    public function up(): void
    {
        $new = [
            'streamer_log',
            'ledger',
            'shipping_surcharge',
            'profit_share_packet',
            'streamer_analytics',
            'streamer_statement',
        ];

        try {
            $raw = Setting::get('enabled_admin_features');
        } catch (\Throwable) {
            return; // settings table not ready — fresh installs default to all-on
        }

        if (! is_string($raw) || trim($raw) === '') {
            return; // unset → already defaults to all features enabled
        }

        $list = json_decode($raw, true);
        if (! is_array($list)) {
            return;
        }

        $merged = array_values(array_unique(array_merge($list, $new)));
        Setting::set('enabled_admin_features', json_encode($merged));
    }

    public function down(): void
    {
        // No-op: leaving the features enabled is harmless.
    }
};
