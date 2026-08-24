<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\RespectsRoleVisibility;
use Filament\Pages\Page;
use App\Models\Streamer;
use App\Support\NavVisibility;

class StreamerHub extends Page
{
    use RespectsRoleVisibility;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-home-modern';
    protected static ?string $navigationLabel = 'Streamer Hub';
    protected static ?int $navigationSort = 5;

    public function getTitle(): string
    {
        return 'Streamer Hub';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Streamer';
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
        return 'filament.pages.streamer-hub';
    }

    private ?Streamer $resolvedStreamer = null;

    public function getStreamer(): Streamer
    {
        // Held for the request. The view asks for this five times to print one
        // person's name and id, and each ask was a fresh query — lazy loading
        // is off, so there is no relation cache doing it for us.
        return $this->resolvedStreamer
            ??= auth()->user()?->streamer ?? abort(403);
    }

    /**
     * The view reads $stats, and nothing was putting it there.
     *
     * getStats() is a plain method: Filament hands a page's view whatever
     * getViewData() returns, and a bare method is not that. So every line of
     * this page referencing $stats threw on an undefined variable — meaning
     * the Streamer Hub was broken for the only people who can open it, and
     * only for them, which is why it stayed unnoticed.
     */
    public function getViewData(): array
    {
        return ['stats' => $this->getStats()];
    }

    public function getStats()
    {
        $streamer = $this->getStreamer();

        return [
            'total_shows' => $streamer->shows()->count(),
            'upcoming_shows' => $streamer->shows()->where('show_date', '>', now())->count(),
            'logs_pending' => $streamer->shows()
                ->where('show_date', '<=', now())
                ->doesntHave('streamerLogEntry')
                ->count(),
            'total_revenue' => $streamer->shows()->sum('gross_revenue'),
            'profit_share_pending' => $streamer->profitSharePackets()
                ->where('status', 'submitted')
                ->count(),
            'profit_share_approved' => $streamer->profitSharePackets()
                ->where('status', 'approved')
                ->count(),
        ];
    }
}
