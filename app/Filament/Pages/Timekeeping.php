<?php

namespace App\Filament\Pages;

use App\Support\AdminModules;
use Filament\Pages\Page;

class Timekeeping extends Page
{
    protected static ?string $title = 'Timekeeping';

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return AdminModules::navigationGroupFor('timekeeping');
    }

    public static function getNavigationSort(): ?int
    {
        return 5;
    }

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-clock';
    }

    public static function canAccess(): bool
    {
        return (auth()->user()?->isAdmin() ?? false) && AdminModules::isEnabled('timekeeping');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return AdminModules::isEnabled('timekeeping');
    }

    public function getView(): string
    {
        return 'filament.pages.timekeeping';
    }
}
