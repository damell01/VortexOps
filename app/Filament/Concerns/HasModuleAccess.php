<?php

namespace App\Filament\Concerns;

use App\Support\AdminModules;

trait HasModuleAccess
{
    protected static string $moduleSlug;

    public static function canAccess(): bool
    {
        return AdminModules::isEnabled(static::$moduleSlug) && static::passesModuleAccessCheck();
    }

    protected static function passesModuleAccessCheck(): bool
    {
        return true;
    }
}
