<?php

namespace App\Filament\Concerns;

use App\Support\NavVisibility;

trait HasAdminNavVisibility
{
    public static function getNavigationItems(): array
    {
        $user = auth()->user();

        if ($user && ! $user->isOwner() && NavVisibility::isHiddenForUser(static::class, $user)) {
            return [];
        }

        return parent::getNavigationItems();
    }
}
