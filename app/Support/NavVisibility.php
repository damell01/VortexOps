<?php

namespace App\Support;

use App\Models\Setting;

class NavVisibility
{
    /** Pages controlled by their own canAccess() rather than per-role nav allow-lists. */
    public const ALWAYS_AVAILABLE = [
        \App\Filament\Pages\HelpCenter::class,
        \App\Filament\Pages\DashboardImproved::class,
        \App\Filament\Pages\EditProfile::class,
        // Everyone's own notification choices, like their profile.
        \App\Filament\Pages\MyNotifications::class,
        \App\Filament\Pages\TwoFactorAuth::class,
        \App\Filament\Pages\TwoFactorVerify::class,
        // New show-first shipment page is part of the existing Streams workflow;
        // its own canAccess() still restricts it to admin/streamer users.
        \App\Filament\Pages\ShowShipments::class,
    ];

    private static ?array $roleMemo = null;
    private static ?array $visibleMemo = null;

    public static function visibleByRole(): array
    {
        return self::$visibleMemo ??= (json_decode(Setting::get('role_visible_nav', '{}'), true) ?: []);
    }

    public static function visibleForRole(string $role): array
    {
        return self::visibleByRole()[$role === 'fulfillment_admin' ? 'admin' : $role] ?? [];
    }

    public static function hasExplicitVisibility(string $role): bool
    {
        return array_key_exists($role === 'fulfillment_admin' ? 'admin' : $role, self::visibleByRole());
    }

    /**
     * Does any role this user holds explicitly name this page as visible?
     *
     * The difference between this and isHiddenForUser() is the difference
     * between a grant and the absence of a ban. A role with no explicit list
     * is "not hidden from" everything, which is why that method cannot be used
     * to open a page a resource has hardcoded shut — see RoleAccess.
     */
    public static function isExplicitlyGrantedTo(string $class, $user): bool
    {
        if (! $user || ! method_exists($user, 'getRoleNames')) {
            return false;
        }

        foreach ($user->getRoleNames() as $role) {
            if (self::hasExplicitVisibility($role) && in_array($class, self::visibleForRole($role), true)) {
                return true;
            }
        }

        return false;
    }

    public static function setVisibleForRole(string $role, array $classes): void
    {
        $map = self::visibleByRole();
        $map[$role] = array_values(array_unique($classes));
        Setting::set('role_visible_nav', json_encode($map));
        self::$visibleMemo = null;
    }

    public static function hiddenByRole(): array
    {
        return self::$roleMemo ??= (json_decode(Setting::get('role_hidden_nav', '{}'), true) ?: []);
    }

    public static function hiddenForRole(string $role): array
    {
        return self::hiddenByRole()[$role === 'fulfillment_admin' ? 'admin' : $role] ?? [];
    }

    public static function setHiddenForRole(string $role, array $classes): void
    {
        $map = self::hiddenByRole();
        $map[$role] = array_values(array_unique($classes));
        Setting::set('role_hidden_nav', json_encode($map));
        self::$roleMemo = null;
    }

    public static function isHiddenForUser(string $class, $user): bool
    {
        if (! $user || (method_exists($user, 'isOwner') && $user->isOwner())) return false;
        if (in_array($class, self::ALWAYS_AVAILABLE, true)) return false;
        // Resource routes resolve to their List/View page class in the menu.
        $navigationClass = is_subclass_of($class, \Filament\Resources\Pages\Page::class)
            ? $class::getResource()
            : $class;
        if ($user->isAdmin() && in_array($navigationClass, [\App\Filament\Pages\AppSettings::class, \App\Filament\Pages\NotificationSettings::class, \App\Filament\Resources\UserResource::class, \App\Filament\Resources\ActivityLogResource::class, \App\Filament\Resources\InventoryMovementResource::class, \App\Filament\Resources\InventoryItemResource::class, \App\Filament\Resources\PalletResource::class, \App\Filament\Resources\VendorResource::class], true)) return false;

        $roleNames = method_exists($user, 'getRoleNames') ? $user->getRoleNames()->all() : [];
        if (empty($roleNames)) return false;

        foreach ($roleNames as $role) {
            if (self::roleGrants($role, $class)) return false;
        }
        return true;
    }

    private static function roleGrants(string $role, string $class): bool
    {
        $navigationClass = is_subclass_of($class, \Filament\Resources\Pages\Page::class)
            ? $class::getResource()
            : $class;
        if (self::hasExplicitVisibility($role)) {
            return in_array($class, self::visibleForRole($role), true)
                || in_array($navigationClass, self::visibleForRole($role), true);
        }
        return ! in_array($class, self::hiddenForRole($role), true)
            && ! in_array($navigationClass, self::hiddenForRole($role), true);
    }

    public static function flushMemo(): void
    {
        self::$roleMemo = null;
        self::$readonlyMemo = null;
        self::$visibleMemo = null;
    }

    private static ?array $readonlyMemo = null;

    public static function readonlyByRole(): array
    {
        return self::$readonlyMemo ??= (json_decode(Setting::get('role_readonly_nav', '{}'), true) ?: []);
    }

    public static function readonlyForRole(string $role): array
    {
        return self::readonlyByRole()[$role === 'fulfillment_admin' ? 'admin' : $role] ?? [];
    }

    public static function setReadonlyForRole(string $role, array $classes): void
    {
        $map = self::readonlyByRole();
        $map[$role] = array_values(array_unique($classes));
        Setting::set('role_readonly_nav', json_encode($map));
        self::$readonlyMemo = null;
    }

    public static function isReadOnlyForUser(string $class, $user): bool
    {
        if (! $user || (method_exists($user, 'isOwner') && $user->isOwner())) return false;

        $roleNames = method_exists($user, 'getRoleNames') ? $user->getRoleNames()->all() : [];
        if (empty($roleNames)) return false;

        $map = self::readonlyByRole();
        foreach ($roleNames as $role) {
            if (! in_array($class, self::readonlyForRole($role), true)) return false;
        }
        return true;
    }
}
