<?php

namespace App\Filament\Concerns;

use App\Support\NavLayout;
use App\Support\NavVisibility;

trait HasAdminNavVisibility
{
    /**
     * Operational/debug pages stay directly accessible by URL and from workflow
     * buttons, but do not clutter the primary sidebar.
     */
    protected static function isUtilityOnlyNavigationPage(): bool
    {
        return in_array(class_basename(static::class), [
            'WhatnotBackfill',
            'WhatnotScraperPage',
            'WhatnotSyncPage',
            'StreamWorkflow',
            'ShowStatusBoard',
        ], true);
    }

    public static function shouldRegisterNavigation(): bool
    {
        if (! auth()->check() || static::isUtilityOnlyNavigationPage()) {
            return false;
        }

        return static::canAccess()
            && ! NavVisibility::isHiddenForUser(static::class, auth()->user());
    }

    public static function getNavigationItems(): array
    {
        if (static::isUtilityOnlyNavigationPage()) {
            return [];
        }

        $user = auth()->user();

        if ($user && ! $user->isOwner() && NavVisibility::isHiddenForUser(static::class, $user)) {
            return [];
        }

        return NavLayout::apply(static::class, parent::getNavigationItems());
    }
}
