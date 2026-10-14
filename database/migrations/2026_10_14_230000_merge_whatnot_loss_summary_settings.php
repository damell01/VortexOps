<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;

/**
 * Two versions of the negative-Whatnot-earnings summary were built at once:
 * "Daily/Weekly Whatnot losses" (two events plus a whatnot_loss_schedules
 * setting) and "Negative Whatnot earnings" (one event whose schedule is
 * immediate, daily or weekly). Only the second remains, so anything saved on
 * the first is carried over rather than lost.
 */
return new class extends Migration
{
    public function up(): void
    {
        $rules = json_decode((string) Setting::get('notification_rules', '{}'), true) ?: [];
        $schedules = json_decode((string) Setting::get('whatnot_loss_schedules', '{}'), true) ?: [];
        $old = array_intersect_key($rules, array_flip(['daily_whatnot_losses', 'weekly_whatnot_losses']));

        if ($old !== [] && ! isset($rules['whatnot_negative'])) {
            // Prefer whichever was switched on; weekly when both were.
            $pick = ($rules['weekly_whatnot_losses']['enabled'] ?? false) ? 'weekly_whatnot_losses'
                : (($rules['daily_whatnot_losses']['enabled'] ?? false) ? 'daily_whatnot_losses' : array_key_first($old));
            $from = $rules[$pick];
            $at = $schedules[$pick] ?? [];
            $day = (int) ($at['day'] ?? 5);
            $rules['whatnot_negative'] = array_merge($from, ['schedule' => [
                'mode' => $pick === 'weekly_whatnot_losses' ? 'weekly' : 'daily',
                'day' => $day === 0 ? 7 : $day,             // Carbon's Sunday is 0; ours is 7
                'time' => $at['time'] ?? ($pick === 'weekly_whatnot_losses' ? '17:00' : '18:00'),
                'skip_empty' => true,
            ]]);
        }

        unset($rules['daily_whatnot_losses'], $rules['weekly_whatnot_losses']);
        Setting::set('notification_rules', json_encode($rules));
    }

    public function down(): void {}
};
