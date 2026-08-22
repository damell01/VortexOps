<?php

namespace App\Support;

use App\Models\Setting;

class NavVisibility
{
    /** Pages controlled by their own canAccess() rather than per-role nav allow-lists. */
    public const ALWAYS_AVAILABLE = [
        \App\Filament\Pages\DashboardImproved::class,
        \App\Filament\Pages\EditProfile::class,
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
        return self::visibleByRole()[$role] ?? [];
    }

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

    public static function hiddenByRole(): array
    {
        return self::$roleMemo ??= (json_decode(Setting::get('role_hidden_nav', '{}'), true) ?: []);
    }

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

    public static function isHiddenForUser(string $class, $user): bool
    {
        if (! $user || (method_exists($user, 'isOwner') && $user->isOwner())) return false;
        if (in_array($class, self::ALWAYS_AVAILABLE, true)) return false;

        $roleNames = method_exists($user, 'getRoleNames') ? $user->getRoleNames()->all() : [];
        if (empty($roleNames)) return false;

        foreach ($roleNames as $role) {
            if (self::roleGrants($role, $class)) return false;
        }
        return true;
    }

    private static function roleGrants(string $role, string $class): bool
    {
        if (self::hasExplicitVisibility($role)) {
            return in_array($class, self::visibleForRole($role), true);
        }
        return ! in_array($class, self::hiddenForRole($role), true);
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
        return self::readonlyByRole()[$role] ?? [];
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
            if (! in_array($class, $map[$role] ?? [], true)) return false;
        }
        return true;
    }
}
