<?php

namespace App\Filament\Concerns;

use App\Support\AdminModules;
use App\Support\NavLayout;
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

        if (\App\Support\RoleAccess::grants(static::class)) {
            return true;
        }

        return static::passesModuleAccessCheck();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess()
            && ! NavVisibility::isHiddenForUser(static::class, auth()->user());
    }

    public static function getNavigationItems(): array
    {
        if (! static::shouldRegisterNavigation()) {
            return [];
        }

        return NavLayout::apply(static::class, parent::getNavigationItems());
    }

    public static function canView(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return \App\Support\RoleAccess::grants(static::class) || parent::canView($record);
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return \App\Support\RoleAccess::allowsEditing(static::class) || parent::canEdit($record);
    }

    protected static function passesModuleAccessCheck(): bool
    {
        return auth()->user() !== null;
    }
}
