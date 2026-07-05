<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HasModuleAccess;
use App\Support\AdminModules;
use Filament\Pages\Page;

class ReceivingGuide extends Page
{
    use HasModuleAccess;

    protected static string $moduleSlug  = 'purchasing';
    protected static string $featureSlug = 'receiving_guide';
    protected static ?string $title = 'Receiving Guide';
    protected static ?string $navigationLabel = 'How It Works';
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-academic-cap';
    protected static ?int $navigationSort = 99;

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return AdminModules::navigationGroupFor('purchasing');
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
