<?php

namespace App\Filament\Concerns;

use App\Support\NavVisibility;

trait HasAdminNavVisibility
{
    /**
     * Check NavVisibility settings to determine if this resource should register
     * in navigation. For resources that also use HasModuleAccess, that trait's
     * shouldRegisterNavigation() will handle both module and visibility checks,
     * so this method is only used by resources using HasAdminNavVisibility alone.
     *
     * Only shows pages that are explicitly marked visible for the user's role.
     */
    public static function shouldRegisterNavigation(): bool
    {
        $user = auth()->user();

        // Owner always sees everything (unless NavVisibility explicitly hides it)
        if ($user?->isOwner()) {
            // Even owner respects NavVisibility if set
            if (NavVisibility::isHiddenForUser(static::class, $user)) {
                return false;
            }
            return true;
        }

        // For non-owners with roles, only show if NOT hidden
        if ($user) {
            // Users must have roles configured; if they do, check if page is hidden for them
            $roleNames = method_exists($user, 'getRoleNames') ? $user->getRoleNames()->all() : [];
            if (!empty($roleNames)) {
                // User has roles: show only if not hidden
                return !NavVisibility::isHiddenForUser(static::class, $user);
            }
            // User has no roles: don't show in nav (unless admin/owner, which is handled above)
            return false;
        }

        // If no user, don't show
        return false;
    }

    public static function getNavigationItems(): array
    {
        $user = auth()->user();

        if ($user && ! $user->isOwner() && NavVisibility::isHiddenForUser(static::class, $user)) {
            return [];
        }

        return parent::getNavigationItems();
    }
}
