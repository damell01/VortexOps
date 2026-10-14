<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * "Log in as" a user, for the owner checking what someone sees.
 *
 * The real user's id is kept in the session; a banner on every page says
 * who you are signed in as and returns you. Only the owner can do this.
 * Both switches are written to the activity log.
 */
class Impersonation
{
    public const SESSION_KEY = 'vx_impersonator_id';

    public static function active(): bool
    {
        return session()->has(self::SESSION_KEY);
    }

    public static function impersonator(): ?User
    {
        $id = session(self::SESSION_KEY);

        return $id ? User::find($id) : null;
    }

    public static function canImpersonate(?User $actor, User $target): bool
    {
        if (! $actor || self::active() || $actor->id === $target->id) {
            return false;
        }

        return $actor->isOwner();
    }

    public static function start(User $actor, User $target): void
    {
        abort_unless(self::canImpersonate($actor, $target), 403);

        activity('auth')->causedBy($actor)->performedOn($target)->log('Logged in as '.$target->name);
        session()->put(self::SESSION_KEY, $actor->id);
        self::switchTo($target);
    }

    public static function stop(): ?User
    {
        $impersonator = self::impersonator();
        $current = Auth::user();
        session()->forget(self::SESSION_KEY);
        if (! $impersonator) {
            return null;
        }

        activity('auth')->causedBy($impersonator)->performedOn($current)->log('Returned from '.($current?->name ?? 'a user'));
        self::switchTo($impersonator);

        return $impersonator;
    }

    private static function switchTo(User $user): void
    {
        Auth::guard('web')->login($user);
        // The session already passed sign-in and two-factor as the real
        // user; keep the session's password check matching who is signed in
        // so it does not log everybody out.
        session()->put('password_hash_web', $user->getAuthPassword());
        session()->put('vx_2fa_verified', true);
        // Channel and business picks belong to whoever made them.
        session()->forget(['active_whatnot_channel_id', 'active_business_id']);
    }
}
