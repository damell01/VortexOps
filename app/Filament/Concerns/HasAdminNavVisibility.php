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
    /**
     * The sidebar follows access, rather than re-deciding it — see
     * HasModuleAccess::shouldRegisterNavigation() for why. Registering a link
     * purely on visibility produced links that 403'd on click, because nothing
     * here consulted whether the page would actually open.
     */
    public static function shouldRegisterNavigation(): bool
    {
        if (! auth()->check()) {
            return false;
        }

        return static::canAccess()
            && ! NavVisibility::isHiddenForUser(static::class, auth()->user());
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
