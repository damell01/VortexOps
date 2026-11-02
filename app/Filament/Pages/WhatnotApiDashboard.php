<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class WhatnotApiDashboard extends Page
{
    protected static ?string $title = 'Whatnot API Health';
    protected static ?string $navigationLabel = 'Whatnot API Health';
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-signal';
    protected static ?int $navigationSort = 98;

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return 'Settings';
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->isOwner() ?? false;
    }

    public function getView(): string
    {
        return 'filament.pages.whatnot-api-dashboard';
    }
}
