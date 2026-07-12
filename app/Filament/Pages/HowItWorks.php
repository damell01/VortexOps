<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HasAdminNavVisibility;
use Filament\Pages\Page;

/**
 * A plain-English tour of how VortexOps fits together — the show lifecycle,
 * the streamer log, inventory, and payouts — so a new team member can orient
 * themselves without a manual.
 */
class HowItWorks extends Page
{
    use HasAdminNavVisibility;

    protected static ?string $title = 'How It Works';
    protected static ?string $navigationLabel = 'How It Works';
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-academic-cap';
    protected static ?int $navigationSort = 1;

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return 'Settings';
    }

    public static function canAccess(): bool
    {
        return auth()->check();
    }

    public function getView(): string
    {
        return 'filament.pages.how-it-works';
    }

    public function getSubheading(): ?string
    {
        return 'A quick tour of how the pieces fit together.';
    }
}
