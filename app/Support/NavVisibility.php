<?php

namespace App\Support;

use App\Models\Setting;

class NavVisibility
{
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
     * it (so any role that shows it wins). A user with no roles sees everything.
     */
    public static function isHiddenForUser(string $class, $user): bool
    {
        if (! $user || (method_exists($user, 'isOwner') && $user->isOwner())) {
            return false;
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
        self::$roleMemo = null;
        self::$readonlyMemo = null;
    }

    // ── Per-role edit capability (view-only pages) ───────────────────────────
    private static ?array $readonlyMemo = null;

    /** @return array<string, array<int,string>>  map of role name => read-only classes */
    public static function readonlyByRole(): array
    {
        return self::$readonlyMemo ??= (json_decode(Setting::get('role_readonly_nav', '{}'), true) ?: []);
    }

    /** @return array<int,string> read-only page classes for a single role */
    public static function readonlyForRole(string $role): array
    {
        return self::readonlyByRole()[$role] ?? [];
    }

    public static function setReadonlyForRole(string $role, array $classes): void
    {
        $map = self::readonlyByRole();
        $map[$role] = array_values(array_unique($classes));
        Setting::set('role_readonly_nav', json_encode($map));
        self::$readonlyMemo = null;
    }

    /**
     * Whether create/edit/delete actions should be blocked for a given user on a
     * given page — the page stays visible/viewable, but write actions don't.
     * Mirrors isHiddenForUser(): the owner is never read-only, a user with no
     * roles is never read-only, and a page is read-only only when EVERY one of
     * the user's roles marks it read-only (any role that grants edit wins).
     */
    public static function isReadOnlyForUser(string $class, $user): bool
    {
        if (! $user || (method_exists($user, 'isOwner') && $user->isOwner())) {
            return false;
        }

        $roleNames = method_exists($user, 'getRoleNames') ? $user->getRoleNames()->all() : [];
        if (empty($roleNames)) {
            return false;
        }

        $map = self::readonlyByRole();
        foreach ($roleNames as $role) {
            if (! in_array($class, $map[$role] ?? [], true)) {
                return false; // this role grants edit access
            }
        }

        return true; // every role marks it read-only
    }
}
