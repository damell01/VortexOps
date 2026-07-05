<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class HorizonDashboard extends Page
{
    protected static ?string $title = 'Queue Monitor';
    protected static ?string $navigationLabel = 'Queue Monitor';
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-circle-stack';
    protected static ?int $navigationSort = 99;

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return 'Settings';
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();
        return $user?->isOwner() || $user?->isAdmin();
    }

    public function getView(): string
    {
        return 'filament.pages.horizon-dashboard';
    }
}
