<?php

namespace App\Filament\Concerns;

use App\Support\AdminModules;

trait HasModuleAccess
{
    public static function canAccess(): bool
    {
        if (auth()->user()?->isOwner()) {
            return true;
        }
        if (! AdminModules::isEnabled(static::$moduleSlug)) {
            return false;
        }
        if (isset(static::$featureSlug) && ! AdminModules::isFeatureEnabled(static::$featureSlug)) {
            return false;
        }
        return static::passesModuleAccessCheck();
    }

    public static function shouldRegisterNavigation(): bool
    {
        if (auth()->user()?->isOwner()) {
            return true;
        }
        if (! AdminModules::isEnabled(static::$moduleSlug)) {
            return false;
        }
        if (isset(static::$featureSlug) && ! AdminModules::isFeatureEnabled(static::$featureSlug)) {
            return false;
        }
        return true;
    }

    protected static function passesModuleAccessCheck(): bool
    {
        $user = auth()->user();
        return ($user?->isAdmin() || $user?->isOwner()) ?? false;
    }
}
