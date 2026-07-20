<?php

namespace App\Filament\Resources\StreamerLogResource\Pages;

use App\Filament\Resources\StreamerLogResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListStreamerLogEntries extends ListRecords
{
    protected static string $resource = StreamerLogResource::class;

    public function getSubheading(): ?string
    {
        $user = auth()->user();
        if ($user && $user->isStreamer() && ! $user->isAdmin() && ! $user->isOwner()) {
            return 'Your shows to review — open one to map the items you sold, set costs, then mark it reviewed for admin approval.';
        }

        return 'Per-show streamer logs. Review streamer submissions and approve them, or send one back to reopen it.';
    }

    /** Quick filter presets. The "To Review" count respects per-streamer scoping. */
    public function getTabs(): array
    {
        $tabs = [
            'all' => Tab::make('All'),

            'to_review' => Tab::make('To Review')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'pending'))
                ->badge(StreamerLogResource::getEloquentQuery()->where('status', 'pending')->count())
                ->badgeColor('warning'),

            'reviewed' => Tab::make('Reviewed')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', ['streamer_reviewed', 'admin_approved'])),

            'approved' => Tab::make('Approved')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'admin_approved')),
        ];

        // Fulfillment review only applies to pwe_labels-payout streamers, and only
        // once admin_approved — shown as its own tab so it doesn't get lost among
        // every other approved entry.
        $needsFulfillment = StreamerLogResource::getEloquentQuery()
            ->where('status', 'admin_approved')
            ->whereNull('fulfillment_reviewed_at')
            ->whereHas('streamer', fn (Builder $q) => $q->where('payout_type', 'pwe_labels'))
            ->count();

        if ($needsFulfillment > 0) {
            $tabs['needs_fulfillment'] = Tab::make('Needs Fulfillment Review')
                ->modifyQueryUsing(fn (Builder $query) => $query
                    ->where('status', 'admin_approved')
                    ->whereNull('fulfillment_reviewed_at')
                    ->whereHas('streamer', fn (Builder $sq) => $sq->where('payout_type', 'pwe_labels')))
                ->badge($needsFulfillment)
                ->badgeColor('info');
        }

        return $tabs;
    }
}
