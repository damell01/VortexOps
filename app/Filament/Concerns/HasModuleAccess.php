<?php

namespace App\Filament\Concerns;

use App\Support\AdminModules;

trait HasModuleAccess
{
    public static function canAccess(): bool
    {
        return AdminModules::isEnabled(static::$moduleSlug) && static::passesModuleAccessCheck();
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
