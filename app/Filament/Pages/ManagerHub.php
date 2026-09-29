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

        return \Illuminate\Support\Facades\Cache::remember("manager_hub:stats:{$manager->id}", 60, function () use ($manager): array {
            $query = ProfitSharePacket::query();
            if (! $manager->isAdmin()) {
                $query->whereIn('streamer_id', $manager->managedStreamers()->select('streamers.id'));
            }

            $row = $query
                ->selectRaw("SUM(CASE WHEN status = 'submitted' THEN 1 ELSE 0 END) as pending_review")
                ->selectRaw("SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved")
                ->selectRaw("SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected")
                ->first();

            return [
                'pending_review' => (int) ($row->pending_review ?? 0),
                'approved' => (int) ($row->approved ?? 0),
                'rejected' => (int) ($row->rejected ?? 0),
                'managed_streamers' => $manager->isAdmin()
                    ? \App\Models\Streamer::count()
                    : $manager->managedStreamers()->count(),
            ];
        });
    }
}
