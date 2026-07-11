<?php

namespace App\Filament\Widgets;

use App\Models\Show;
use App\Models\StreamerLogEntry;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StreamerOverviewWidget extends BaseWidget
{
    protected static ?int $sort = 0;

    /** Streamer self-service overview — only for streamer accounts (not admins). */
    public static function canView(): bool
    {
        $user = auth()->user();
        return ($user?->isStreamer() && ! $user->isAdmin() && ! $user->isOwner()) ?? false;
    }

    protected function getStats(): array
    {
        $streamerId = auth()->user()?->streamer?->id;
        if (! $streamerId) {
            return [];
        }

        $mStart = now()->startOfMonth()->toDateString();
        $mEnd   = now()->endOfMonth()->toDateString();

        $pending = StreamerLogEntry::where('streamer_id', $streamerId)
            ->where('status', 'pending')
            ->count();

        $myShowsMonth = Show::whereHas('streamers', fn ($q) => $q->where('streamers.id', $streamerId))
            ->whereBetween('show_date', [$mStart, $mEnd])
            ->count();

        $myGrossMonth = (float) Show::whereHas('streamers', fn ($q) => $q->where('streamers.id', $streamerId))
            ->whereBetween('show_date', [$mStart, $mEnd])
            ->sum('gross_revenue');

        $outstanding = (float) StreamerLogEntry::where('streamer_id', $streamerId)
            ->get(['total_due', 'total_paid'])
            ->sum(fn ($e) => max(0, (float) $e->total_due - (float) $e->total_paid));

        return [
            Stat::make('Shows to Review', number_format($pending))
                ->description($pending > 0 ? 'Add your items sold & costs' : 'You\'re all caught up')
                ->icon('heroicon-o-clipboard-document-list')
                ->color($pending > 0 ? 'warning' : 'success'),

            Stat::make('My Shows · ' . now()->format('M'), number_format($myShowsMonth))
                ->description('This month')
                ->icon('heroicon-o-video-camera')
                ->color('primary'),

            Stat::make('My Gross · ' . now()->format('M'), '$' . number_format($myGrossMonth, 2))
                ->description('Across your shows this month')
                ->icon('heroicon-o-banknotes')
                ->color('info'),

            Stat::make('Outstanding Due', '$' . number_format($outstanding, 2))
                ->description('Across all your logged shows')
                ->icon('heroicon-o-currency-dollar')
                ->color($outstanding > 0 ? 'warning' : 'gray'),
        ];
    }
}
