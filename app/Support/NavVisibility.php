<?php

namespace App\Support;

use App\Models\Setting;

class NavVisibility
{
    private static ?array $memo = null;

    public static function hiddenForAdmins(): array
    {
        return self::$memo ??= json_decode(Setting::get('hidden_admin_nav', '[]'), true) ?? [];
    }

    public static function isHiddenForAdmin(string $class): bool
    {
        return in_array($class, self::hiddenForAdmins(), true);
    }

    public static function setHiddenForAdmins(array $classes): void
    {
        Setting::set('hidden_admin_nav', json_encode(array_values($classes)));
        self::$memo = null;
    }

    // ── Per-role nav visibility ───────────────────────────────────────────────
    private static ?array $roleMemo = null;

    /** @return array<string, array<int,string>>  map of role name => hidden classes */
    public static function hiddenByRole(): array
    {
        return self::$roleMemo ??= (json_decode(Setting::get('role_hidden_nav', '{}'), true) ?: []);
    }

    /** @return array<int,string> hidden page classes for a single role */
    public static function hiddenForRole(string $role): array
    {
        return self::hiddenByRole()[$role] ?? [];
    }

    public static function setHiddenForRole(string $role, array $classes): void
    {
        $map = self::hiddenByRole();
        $map[$role] = array_values(array_unique($classes));
        Setting::set('role_hidden_nav', json_encode($map));
        self::$roleMemo = null;
    }

    /**
     * Whether a page is hidden in the nav for a given user. The owner always sees
     * everything. A page is hidden only when EVERY one of the user's roles hides
     * it (so any role that shows it wins) — plus the legacy admin-hidden list.
     */
    public static function isHiddenForUser(string $class, $user): bool
    {
        if (! $user || (method_exists($user, 'isOwner') && $user->isOwner())) {
            return false;
        }

        // Legacy global "hidden from admins" list.
        if (method_exists($user, 'isAdmin') && $user->isAdmin() && self::isHiddenForAdmin($class)) {
            return true;
        }

        $roleNames = method_exists($user, 'getRoleNames') ? $user->getRoleNames()->all() : [];
        if (empty($roleNames)) {
            return false;
        }

        $map = self::hiddenByRole();
        foreach ($roleNames as $role) {
            if (! in_array($class, $map[$role] ?? [], true)) {
                return false; // this role grants visibility
            }
        }

        return true; // every role hides it
    }

    public static function flushMemo(): void
    {
        self::$memo = null;
        self::$roleMemo = null;
    }
}
