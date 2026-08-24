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
     * Opening a record, and changing one.
     *
     * canAccess() only governs the list page. A record page asks canView() /
     * canEdit(), which fall through to the policy — so a role granted "Shows"
     * on Roles & Permissions could open the list and then take a 403 on any
     * show in it, because the grant never reached the Shield permission the
     * policy checks. Granting a page now means the records on it too.
     *
     * Both fall back to the parent, so a role with no explicit list is decided
     * by the policy exactly as before.
     */
    public static function canView(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return \App\Support\RoleAccess::grants(static::class) || parent::canView($record);
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return \App\Support\RoleAccess::allowsEditing(static::class) || parent::canEdit($record);
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
