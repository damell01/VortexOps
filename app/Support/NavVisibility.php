<?php

namespace App\Support;

use App\Models\Setting;

class NavVisibility
{
    /**
     * Pages every signed-in user keeps, whatever their roles.
     *
     * Panel plumbing rather than product pages: the dashboard is where login
     * lands, and the other three are how somebody manages their own profile
     * and two-factor settings. The Roles screen does not offer them, so they
     * never appear in a saved allow-list — and an allow-list denies whatever
     * it does not name. Without this they were denied the moment a role was
     * saved, which 403'd the page every login redirects to.
     */
    public const ALWAYS_AVAILABLE = [
        \App\Filament\Pages\DashboardImproved::class,
        \App\Filament\Pages\EditProfile::class,
        \App\Filament\Pages\TwoFactorAuth::class,
        \App\Filament\Pages\TwoFactorVerify::class,
    ];

    // ── Per-role nav visibility ───────────────────────────────────────────────
    private static ?array $roleMemo = null;

    private static ?array $visibleMemo = null;

    /**
     * Pages a role may see, keyed by role name.
     *
     * This is the authority. It used to be the inverse — a list of what each
     * role could NOT see — which meant anything absent from that list was
     * visible by default. Every page added after a role was last saved was
     * therefore granted to it automatically, so the sidebar drifted away from
     * the Roles & Permissions screen with each release.
     *
     * A role present here sees exactly what is listed and nothing else. A role
     * absent from it has never been configured and falls back to the old
     * hide-list, so an install that has not visited the Roles screen yet does
     * not lock everybody out.
     *
     * @return array<string, array<int, string>>
     */
    public static function visibleByRole(): array
    {
        return self::$visibleMemo ??= (json_decode(Setting::get('role_visible_nav', '{}'), true) ?: []);
    }

    /** @return array<int, string> */
    public static function visibleForRole(string $role): array
    {
        return self::visibleByRole()[$role] ?? [];
    }

    /** Whether this role's visible pages have been set explicitly. */
    public static function hasExplicitVisibility(string $role): bool
    {
        return array_key_exists($role, self::visibleByRole());
    }

    public static function setVisibleForRole(string $role, array $classes): void
    {
        $map = self::visibleByRole();
        $map[$role] = array_values(array_unique($classes));

        Setting::set('role_visible_nav', json_encode($map));

        self::$visibleMemo = null;
    }

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
     * Whether a page is hidden for a given user.
     *
     * A page is hidden unless at least one of the user's roles grants it — so
     * where somebody holds several roles, the most permissive wins, which is
     * what "any role that grants access wins" on the Roles screen means.
     *
     * The owner always sees everything, and a user with no roles at all is not
     * filtered. That second case is deliberately left open: assigning nobody a
     * role and then hiding the entire panel from them would lock an install out
     * of itself, and it is the owner's job to grant roles.
     */
    public static function isHiddenForUser(string $class, $user): bool
    {
        if (! $user || (method_exists($user, 'isOwner') && $user->isOwner())) {
            return false;
        }

        // Checked before roles: these are not role-controlled, so no allow-list
        // will ever name them.
        if (in_array($class, self::ALWAYS_AVAILABLE, true)) {
            return false;
        }

        $roleNames = method_exists($user, 'getRoleNames') ? $user->getRoleNames()->all() : [];
        if (empty($roleNames)) {
            return false;
        }

        foreach ($roleNames as $role) {
            if (self::roleGrants($role, $class)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Whether one role may see one page.
     *
     * Configured roles are read as an allow-list: listed means granted, absent
     * means not, including pages that did not exist when the role was saved.
     * That last part is the point — under the old hide-list a new page was
     * granted to every role the moment it shipped.
     */
    private static function roleGrants(string $role, string $class): bool
    {
        if (self::hasExplicitVisibility($role)) {
            return in_array($class, self::visibleForRole($role), true);
        }

        // Never configured: fall back to the hide-list so a fresh install
        // behaves as it did before anyone opened the Roles screen.
        return ! in_array($class, self::hiddenForRole($role), true);
    }

    public static function flushMemo(): void
    {
        self::$roleMemo = null;
        self::$readonlyMemo = null;
        self::$visibleMemo = null;
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
