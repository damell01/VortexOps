<?php

namespace App\Filament\Concerns;

use App\Support\NavVisibility;

trait HasAdminNavVisibility
{
    public static function shouldRegisterNavigation(): bool
    {
        $user = auth()->user();

        // Owner always sees everything; check NavVisibility for other users
        if ($user && ! $user->isOwner() && NavVisibility::isHiddenForUser(static::class, $user)) {
            return false;
        }

        return parent::shouldRegisterNavigation();
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
