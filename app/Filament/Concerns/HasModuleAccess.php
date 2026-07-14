<?php

namespace App\Filament\Concerns;

use App\Support\AdminModules;
use App\Support\NavVisibility;

trait HasModuleAccess
{
    public static function canAccess(): bool
    {
        if (! AdminModules::isEnabled(static::$moduleSlug)) {
            return false;
        }
        if (NavVisibility::isHiddenForUser(static::class, auth()->user())) {
            return false;
        }
        return static::passesModuleAccessCheck();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return AdminModules::isEnabled(static::$moduleSlug);
    }

    protected static function passesModuleAccessCheck(): bool
    {
        $user = auth()->user();
        return ($user?->isAdmin() || $user?->isOwner()) ?? false;
    }
}
