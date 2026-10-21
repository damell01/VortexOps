<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HasModuleAccess;
use App\Livewire\StreamerLeaderboard;
use Filament\Pages\Page;

/**
 * The Top Net Shows leaderboard on its own page, linked under Shows so
 * everyone with that tab can open it, not only from a dashboard.
 */
class ShowLeaderboard extends Page
{
    use HasModuleAccess;

    protected static string $moduleSlug = 'streams';
    protected static ?string $title = 'Show Leaderboard';
    protected static ?string $slug = 'show-leaderboard';
    protected static bool $shouldRegisterNavigation = false;

    protected static function passesModuleAccessCheck(): bool
    {
        return StreamerLeaderboard::canView();
    }

    public function getView(): string { return 'filament.pages.show-leaderboard'; }
}
