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

    /**
     * Base access check — subclasses can override for stricter role-specific checks.
     * Now that visibility is controlled by NavVisibility, this just checks basic auth.
     * Resources that need stricter checks (admin-only, etc.) override canAccess() directly.
     */
    protected static function passesModuleAccessCheck(): bool
    {
        return auth()->user() !== null;
    }
}
