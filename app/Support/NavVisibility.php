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

    public static function flushMemo(): void
    {
        self::$memo = null;
    }
}
