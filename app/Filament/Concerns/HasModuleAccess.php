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
        $user = auth()->user();

        // Check module is enabled
        if (! AdminModules::isEnabled(static::$moduleSlug)) {
            return false;
        }

        // Check role visibility (owner always sees everything)
        if ($user && ! $user->isOwner() && NavVisibility::isHiddenForUser(static::class, $user)) {
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
