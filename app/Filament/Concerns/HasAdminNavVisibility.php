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
     */
    public static function shouldRegisterNavigation(): bool
    {
        $user = auth()->user();

        // Owner always sees everything; check NavVisibility for other users
        if ($user && ! $user->isOwner() && NavVisibility::isHiddenForUser(static::class, $user)) {
            return false;
        }

        return parent::shouldRegisterNavigation() ?? true;
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
