<?php

namespace App\Filament\Pages;

use App\Support\AdminModules;
use Filament\Pages\Page;

class ReceivingGuide extends Page
{
    protected static ?string $title = 'Receiving Guide';
    protected static ?string $navigationLabel = 'How It Works';
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-academic-cap';
    protected static ?int $navigationSort = 99;

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return AdminModules::navigationGroupFor('purchasing');
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();
        return ($user?->isAdmin() || $user?->isOwner()) && AdminModules::isEnabled('purchasing');
    }

    public function getView(): string
    {
        return 'filament.pages.receiving-guide';
    }

    // Active tab for the guide sections
    public string $tab = 'workflow';

    public function setTab(string $tab): void
    {
        $this->tab = $tab;
    }
}
