<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use App\Filament\Concerns\HasAdminNavVisibility;

class HorizonDashboard extends Page
{
    use HasAdminNavVisibility;

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

    // Diagnostic tool — only surface it in the owner's menu (still URL-reachable).
    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->isOwner() ?? false;
    }

    public function getView(): string
    {
        return 'filament.pages.horizon-dashboard';
    }

    public function getSubheading(): ?string
    {
        return 'Background job queue status — what\'s running, what\'s failed, and how backed up things are.';
    }
}
