<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\RespectsRoleVisibility;
use Filament\Pages\Page;
use App\Models\User;
use App\Models\ProfitSharePacket;
use App\Support\NavVisibility;

class ManagerHub extends Page
{
    use RespectsRoleVisibility;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';
    protected static ?string $navigationLabel = 'Manager Hub';
    protected static ?int $navigationSort = 15;

    public function getTitle(): string
    {
        return 'Manager Hub';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Management';
    }

    public static function shouldRegisterNavigation(): bool
    {
        // Nav visibility is configured per role in Settings; without this
        // check an override here silently ignored that setting and the link
        // stayed in the sidebar regardless.
        if (NavVisibility::isHiddenForUser(static::class, auth()->user())) {
            return false;
        }

        return false;
    }

    public function getView(): string
    {
        return 'filament.pages.manager-hub';
    }

    public function getManager(): User
    {
        return auth()->user() ?? abort(403);
    }

    /** Filament passes page view data through here; the view reads $stats. */
    public function getViewData(): array
    {
        return ['stats' => $this->getStats()];
    }

    public function getStats()
    {
        $manager = $this->getManager();

        $query = ProfitSharePacket::query();
        if (!$manager->isAdmin()) {
            $query->whereIn('streamer_id', $manager->managedStreamers()->pluck('streamers.id'));
        }

        return [
            'pending_review' => (clone $query)->where('status', 'submitted')->count(),
            'approved' => (clone $query)->where('status', 'approved')->count(),
            'rejected' => (clone $query)->where('status', 'rejected')->count(),
            'managed_streamers' => $manager->isAdmin()
                ? \App\Models\Streamer::count()
                : $manager->managedStreamers()->count(),
        ];
    }
}
