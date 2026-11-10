<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/** Who may do what in the Fulfillment Center. One place, so every page and service agrees. */
final class FulfillmentAccess
{
    /** Fulfillment admins, admins and the owner run the floor. */
    public static function isManager(?User $user): bool
    {
        return (bool) ($user && ($user->isOwner() || $user->isAdmin() || $user->isFulfillmentAdmin()));
    }

    /** Anyone who works in fulfillment, managers included. */
    public static function isMember(?User $user): bool
    {
        return (bool) ($user && (self::isManager($user) || $user->isFulfillment()));
    }

    /** May this person act on this run (pack, scan, flag)? Managers always; members when they are on it. */
    public static function canWorkRun(?User $user, int $runId): bool
    {
        if (! $user) return false;
        if (self::isManager($user)) return true;
        if (! $user->isFulfillment()) return false;

        return DB::table('fulfillment_run_packers')->where('run_id', $runId)->where('user_id', $user->id)->exists()
            || DB::table('fulfillment_runs')->join('show_fulfillment_user', 'show_fulfillment_user.show_id', '=', 'fulfillment_runs.show_id')
                ->where('fulfillment_runs.id', $runId)->where('show_fulfillment_user.user_id', $user->id)->exists();
    }

    public static function moduleEnabled(): bool
    {
        return AdminModules::isEnabled('fulfillment');
    }
}
