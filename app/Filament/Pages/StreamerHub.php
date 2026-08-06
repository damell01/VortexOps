<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use App\Models\Streamer;

class StreamerHub extends Page
{
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
        return auth()->user()?->isStreamer() ?? false;
    }

    public function getView(): string
    {
        return 'filament.pages.streamer-hub';
    }

    public function getStreamer(): Streamer
    {
        return auth()->user()?->streamer ?? abort(403);
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
