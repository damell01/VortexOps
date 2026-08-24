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

        // A role that explicitly names this page on Roles & Permissions has
        // been granted it, and that is the answer. Without this the hardcoded
        // check below always won and the screen could only ever take access
        // away, never give it.
        if (\App\Support\RoleAccess::grants(static::class)) {
            return true;
        }

        return static::passesModuleAccessCheck();
    }

    /**
     * The sidebar follows access, rather than re-deciding it.
     *
     * These two used to be written independently and drifted apart in both
     * directions: pages a role could open with no link to them, and links that
     * 403'd when clicked. Deriving the link from canAccess() means the sidebar
     * and the Roles & Permissions page cannot disagree — if you are allowed the
     * page and have not hidden it, you get the link; otherwise you do not.
     *
     * A page that deliberately stays out of the sidebar (a sub-page reached
     * from somewhere else) still overrides this and returns false.
     */
    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess()
            && ! NavVisibility::isHiddenForUser(static::class, auth()->user());
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
