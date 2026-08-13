<?php

namespace App\Filament\Resources\StreamerLogResource\Pages;

use App\Filament\Resources\StreamerLogResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListStreamerLogEntries extends ListRecords
{
    protected static string $resource = StreamerLogResource::class;

    public function getView(): string
    {
        return 'filament.resources.streamer-log-resource.pages.list-streamer-log-entries';
    }

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
        $count = fn (callable $filter): int => $filter(StreamerLogResource::getEloquentQuery())->count();

        $tabs = [
            'all' => Tab::make('All'),

            // Started but not yet sent for review.
            'in_progress' => Tab::make('In Progress')
                ->modifyQueryUsing(fn (Builder $query) => $query
                    ->where('status', 'pending')
                    ->whereNull('submitted_at'))
                ->badge($count(fn ($q) => $q->where('status', 'pending')->whereNull('submitted_at'))),

            'submitted' => Tab::make('Submitted')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'streamer_reviewed'))
                ->badge($count(fn ($q) => $q->where('status', 'streamer_reviewed')))
                ->badgeColor('info'),

            // Sent back by an admin; the streamer needs to revise and resubmit.
            'changes_requested' => Tab::make('Changes Requested')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'changes_requested'))
                ->badge($count(fn ($q) => $q->where('status', 'changes_requested')))
                ->badgeColor('warning'),

            'approved' => Tab::make('Approved')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'admin_approved'))
                ->badgeColor('success'),

            'paid' => Tab::make('Paid')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereNotNull('total_paid')->where('total_paid', '>', 0)),
        ];

        // Fulfillment review tab — only show if column exists and there are items
        if (\Illuminate\Support\Facades\Schema::hasColumn('streamer_log_entries', 'fulfillment_reviewed_at')) {
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
        }

        return $tabs;
    }
}
