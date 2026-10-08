<?php

namespace App\Filament\Resources\StreamerLogResource\Pages;

use App\Filament\Resources\StreamerLogResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListStreamerLogEntries extends ListRecords
{
    protected static string $resource = StreamerLogResource::class;

    /** Per-request memo — the tiles run several aggregates over the same set. */
    protected ?array $statsMemo = null;

    public function getView(): string
    {
        return 'filament.resources.streamer-log-resource.pages.list-streamer-log-entries';
    }

    /**
     * Headline tiles above the table, scoped through the resource query so a
     * streamer's numbers cover their own entries only — same rows they see.
     */
    public function reportStartDate(): ?string { return StreamerLogResource::reportStartDate(); }

    public function getStats(): array
    {
        if ($this->statsMemo !== null) return $this->statsMemo;
        $definitions = [
            'submissions' => ['Total Submissions', 'Filed reports since reporting began', 'heroicon-o-document-text'],
            'submitted' => ['Admin Review', 'Submitted and waiting', 'heroicon-o-clock'],
            'edit_requested' => ['Edit Requests', 'Streamers asking to reopen', 'heroicon-o-lock-open'],
            'changes_requested' => ['Changes Requested', 'Needs streamer updates', 'heroicon-o-arrow-uturn-left'],
            'approved' => ['Approved', 'Ready for the next step', 'heroicon-o-check-circle'],
        ];
        $stats = [];
        foreach ($definitions as $key => [$label, $sub, $icon]) {
            $count = $this->filterInbox(StreamerLogResource::getEloquentQuery(), $key)->count();
            $stats[$key] = compact('label', 'sub', 'icon', 'count') + ['value' => number_format($count)];
        }
        return $this->statsMemo = $stats;
    }

    protected function filterInbox(Builder $query, string $key): Builder
    {
        return match ($key) {
            'submissions' => $query->where(fn (Builder $q) => $q->whereNotNull('submitted_at')->orWhereIn('status', ['streamer_reviewed','admin_approved','changes_requested'])),
            'submitted' => $query->where('status', 'streamer_reviewed'),
            'edit_requested' => $query->whereNotNull('revision_requested_at'),
            'changes_requested' => $query->where('status', 'changes_requested'),
            'approved' => $query->where('status', 'admin_approved')->where(fn (Builder $q) => $q->where('approval_status','approved')->orWhereNull('approval_status')),
            'attention' => $query->where(fn (Builder $q) => $q->where('status', 'streamer_reviewed')->orWhereNotNull('revision_requested_at')),
            default => $query,
        };
    }

    public function selectInboxStatus(string $key): void
    {
        if (! array_key_exists($key, $this->getTabs())) return;
        $this->activeTab = $key;
        $this->resetPage($this->getTablePaginationPageName());
    }

    public function reopenRequests()
    {
        return StreamerLogResource::getEloquentQuery()->whereNotNull('revision_requested_at')->oldest('revision_requested_at')->limit(6)->get();
    }

    public function reopenRequestedReport(int $id): void
    {
        abort_unless(auth()->user()?->isAdmin(), 403);
        \Illuminate\Support\Facades\DB::transaction(function () use ($id) {
            $report = StreamerLogResource::getEloquentQuery()->lockForUpdate()->findOrFail($id);
            abort_unless($report->hasPendingRevisionRequest() && $report->isSubmitted(), 422);
            $report->reopenForEditing();
        });
        $this->statsMemo = null;
        \Filament\Notifications\Notification::make()->title('Report reopened for the streamer')->success()->send();
    }

    public function getSubheading(): ?string
    {
        $user = auth()->user();
        if ($user && $user->isStreamer() && ! $user->isAdmin() && ! $user->isOwner()) {
            return 'Your shows to review — open one to map the items you sold, set costs, then mark it reviewed for admin approval.';
        }

        return 'Review streamer submissions in one queue. Approve clean reports, request corrections, and hand approved inventory work to fulfillment.';
    }

    /** Quick filter presets. The "To Review" count respects per-streamer scoping. */
    public function getDefaultActiveTab(): string|int|null
    {
        $user = auth()->user();
        return ($user?->isAdmin() || $user?->isOwner() || $user?->isFulfillmentAdmin()) ? 'attention' : 'all';
    }

    public function getTabs(): array
    {
        $count = fn (callable $filter): int => $filter(StreamerLogResource::getEloquentQuery())->count();

        $tabs = [
            'all' => Tab::make('All reports'),
            'attention' => Tab::make('Needs your attention')->modifyQueryUsing(fn (Builder $q) => $this->filterInbox($q, 'attention')),
            'submissions' => Tab::make('Submissions')->modifyQueryUsing(fn (Builder $q) => $this->filterInbox($q, 'submissions')),

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

            // Filed, locked, and the streamer has asked for it back. Waiting on
            // an admin to say yes or no.
            'edit_requested' => Tab::make('Edit Requests')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereNotNull('revision_requested_at'))
                ->badge($count(fn ($q) => $q->whereNotNull('revision_requested_at')))
                ->badgeColor('danger'),

            // Sent back by an admin; the streamer needs to revise and resubmit.
            'changes_requested' => Tab::make('Changes Requested')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'changes_requested'))
                ->badge($count(fn ($q) => $q->where('status', 'changes_requested')))
                ->badgeColor('warning'),

            'approved' => Tab::make('Approved')
                ->modifyQueryUsing(fn (Builder $query) => $this->filterInbox($query, 'approved'))
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
