<?php

namespace App\Support;

use App\Models\User;
use App\Notifications\AccountInviteNotification;
use Illuminate\Support\Facades\URL;

/**
 * Invitations to a new (or locked-out) account: a signed link to set their
 * own password, good for seven days. The link carries a fingerprint of the
 * account's current password, so it stops working as soon as a password is
 * set: an invite can be used once.
 */
final class AccountInvite
{
    public const DAYS = 7;

    public static function url(User $user): string
    {
        return URL::temporarySignedRoute('account.invite', now()->addDays(self::DAYS), [
            'user' => $user->getKey(),
            'v' => self::fingerprint($user),
        ]);
    }

    /** Whether a link's fingerprint still matches the account (no password set since it was sent). */
    public static function current(User $user, ?string $fingerprint): bool
    {
        return is_string($fingerprint) && hash_equals(self::fingerprint($user), $fingerprint);
    }

    /**
     * Email the invite. Returns the link either way so the person sending it
     * can copy it when email is not set up.
     *
     * @return array{sent: bool, url: string, error: ?string}
     */
    public static function send(User $user, ?User $invitedBy = null): array
    {
        $url = self::url($user);
        try {
            $user->notifyNow(new AccountInviteNotification($url, $invitedBy?->name, self::DAYS));

            return ['sent' => true, 'url' => $url, 'error' => null];
        } catch (\Throwable $e) {
            report($e);

            return ['sent' => false, 'url' => $url, 'error' => $e->getMessage()];
        }
    }

    private static function fingerprint(User $user): string
    {
        return substr(hash_hmac('sha256', (string) $user->getRawOriginal('password') . '|' . $user->getKey(), (string) config('app.key')), 0, 24);
    }
}
