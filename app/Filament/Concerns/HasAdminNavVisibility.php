<?php

namespace App\Filament\Concerns;

use App\Support\NavLayout;
use App\Support\NavVisibility;

trait HasAdminNavVisibility
{
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

        return NavLayout::apply(static::class, parent::getNavigationItems());
    }
}
