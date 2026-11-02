<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class WhatnotApiDashboard extends Page
{
    protected static ?string $title = 'Whatnot API Control Center';
    protected static ?string $navigationLabel = 'Whatnot API Control Center';
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-signal';
    protected static ?int $navigationSort = 98;

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return 'Owner Tools';
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
