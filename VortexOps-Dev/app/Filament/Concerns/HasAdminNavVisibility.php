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

        // For non-owners, check NavVisibility — users with no roles see everything
        if ($user) {
            return !NavVisibility::isHiddenForUser(static::class, $user);
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
