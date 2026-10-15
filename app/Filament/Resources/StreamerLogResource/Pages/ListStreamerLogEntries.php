<?php

namespace App\Filament\Resources\StreamerLogResource\Pages;

use App\Filament\Resources\StreamerLogResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListStreamerLogEntries extends ListRecords
{
    protected static string $resource = StreamerLogResource::class;

    /** The Sort menu above the cards; see StreamerLogResource::applyInboxSort(). */
    public string $inboxSort = '';

    public function updatedInboxSort(): void
    {
        $this->resetPage($this->getTablePaginationPageName());
    }

    /**
     * How submitted reports are approved, from Settings → Post-Show Workflow.
     *
     * @return array{tone:string, title:string, body:string, url:?string}
     */
    public function reviewPolicy(): array
    {
        $url = \App\Filament\Pages\AppSettings::canAccess() ? \App\Filament\Pages\AppSettings::getUrl() . '#show-workflow' : null;

        return match ((string) \App\Models\Setting::get('show_report_review_policy', 'required')) {
            'auto' => ['tone' => 'green', 'title' => 'Auto-approval enabled', 'body' => 'Every report approves itself on submission.', 'url' => $url],
            'exceptions_only' => ['tone' => 'green', 'title' => 'Auto-approval enabled', 'body' => 'Reports approve on submission unless an issue needs review.', 'url' => $url],
            default => ['tone' => 'violet', 'title' => 'Admin review required', 'body' => 'Every submitted report waits here until an admin approves it.', 'url' => $url],
        };
    }

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
            'submissions' => ['Total', 'Filed reports since reporting began', 'heroicon-o-document-text'],
            'submitted' => ['Admin review', 'Submitted and waiting', 'heroicon-o-clock'],
            'edit_requested' => ['Edit requests', 'Streamers asking to reopen', 'heroicon-o-pencil-square'],
            'changes_requested' => ['Changes requested', 'Needs streamer updates', 'heroicon-o-arrow-uturn-left'],
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
        if (! $query->getModel()) $query->setModel(new \App\Models\StreamerLogEntry);
        return match ($key) {
            'submissions' => $query->whereNested(fn (\Illuminate\Database\Query\Builder $q) => $q->whereNotNull('submitted_at')->orWhereIn('status', ['streamer_reviewed','admin_approved','changes_requested'])),
            'submitted' => $query->where('status', 'streamer_reviewed'),
            'edit_requested' => $query->whereNotNull('revision_requested_at'),
            'changes_requested' => $query->where('status', 'changes_requested'),
            'approved' => $query->where('status', 'admin_approved')->whereNested(fn (\Illuminate\Database\Query\Builder $q) => $q->where('approval_status','approved')->orWhereNull('approval_status')),
            // Everything still open: waiting on an admin, a streamer asking
            // to reopen, or sent back and not yet fixed.
            'attention' => $query->whereNested(fn (\Illuminate\Database\Query\Builder $q) => $q->whereIn('status', ['streamer_reviewed', 'changes_requested'])->orWhereNotNull('revision_requested_at')),
            default => $query,
        };
    }

    /** Counts beside the three tabs. @return array<string,int> */
    public function tabCounts(): array
    {
        return $this->tabCountsMemo ??= collect(['attention', 'all', 'approved'])
            ->mapWithKeys(fn ($key) => [$key => $this->filterInbox(StreamerLogResource::getEloquentQuery(), $key)->count()])
            ->all();
    }

    protected ?array $tabCountsMemo = null;

    /** Pickers for the filter row. @return array{streamers: array<int,string>, channels: array<int,string>} */
    public function filterOptions(): array
    {
        $user = auth()->user();

        return [
            'streamers' => ($user?->isAdmin() || $user?->isOwner() || $user?->isFulfillmentAdmin())
                ? \App\Models\Streamer::orderBy('name')->pluck('name', 'id')->all() : [],
            'channels' => \App\Models\WhatnotChannel::orderBy('name')->pluck('name', 'id')->all(),
        ];
    }

    public function selectInboxStatus(string $key): void
    {
        if (! array_key_exists($key, $this->getTabs())) return;
        $this->activeTab = $key;
        $this->resetPage($this->getTablePaginationPageName());
    }

    /** Every report waiting on an admin, and the one that has waited longest. */
    public function reviewQueue(): array
    {
        $query = StreamerLogResource::getEloquentQuery()->where('status', 'streamer_reviewed');
        $next = (clone $query)->orderByRaw('submitted_at is null')->oldest('submitted_at')->oldest('id')->first();

        return [
            'count' => $this->getStats()['submitted']['count'],
            'next' => $next ? StreamerLogResource::getUrl('edit', ['record' => $next]) : null,
        ];
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

    public function getTitle(): string
    {
        return 'Show Report Inbox';
    }

    public function getHeading(): string
    {
        return 'Show report inbox';
    }

    public function getSubheading(): ?string
    {
        $user = auth()->user();
        if ($user && $user->isStreamer() && ! $user->isAdmin() && ! $user->isOwner()) {
            return 'Your show reports. Open one to log what sold, then submit it for review.';
        }

        $from = $this->reportStartDate();

        return 'Manage submitted reports and resolve exceptions.'
            . ($from ? ' Shows from ' . \Illuminate\Support\Carbon::parse($from)->format('M j, Y') . ' onward; counts follow the selected channel.' : ' Counts follow the selected channel.');
    }

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('refresh')
                ->label('Refresh')
                ->icon('heroicon-m-arrow-path')
                ->color('gray')
                ->action(fn () => $this->statsMemo = null),
            \Filament\Actions\Action::make('export_csv')
                ->label('Export CSV')
                ->icon('heroicon-m-arrow-down-tray')
                ->color('gray')
                ->action(fn () => $this->exportCsv()),
        ];
    }

    /** The cards are drawn from a view, so the export writes the same figures flat. */
    public function exportCsv(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $user = auth()->user();
        $staff = $user?->isAdmin() || $user?->isOwner() || $user?->isFulfillmentAdmin();
        $rows = $this->getFilteredSortedTableQuery()->limit(20000)->get();

        return response()->streamDownload(function () use ($rows, $staff, $user) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Show date', 'Show', 'Channel', 'Streamer', 'Status', 'Sales', 'Product cost', 'Pay', 'Pay status', 'Missing', 'Submitted'], ',', '"', '');
            foreach ($rows as $r) {
                $mine = $user?->streamer?->id && $user->streamer->id === $r->streamer_id;
                $pay = ($staff || $mine) ? StreamerLogResource::payFor($r) : null;
                fputcsv($out, [
                    $r->show?->show_date?->toDateString(),
                    $r->show?->title,
                    $r->show?->channel?->name,
                    $r->streamer?->name,
                    \App\Models\StreamerLogEntry::statusLabels()[$r->status] ?? $r->status,
                    $r->gross_revenue ?? $r->show?->gross_revenue,
                    $r->product_cost,
                    $pay['amount'] ?? null,
                    $pay['state'] ?? null,
                    $staff ? implode('; ', StreamerLogResource::missingInfo($r)) : null,
                    $r->submitted_at?->toDateTimeString(),
                ], ',', '"', '');
            }
            fclose($out);
        }, 'show-reports-' . now()->format('Y-m-d') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
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
            'attention' => Tab::make('Needs your attention')->modifyQueryUsing(fn (Builder $query) => $this->filterInbox($query, 'attention')),
            'submissions' => Tab::make('Submissions')->modifyQueryUsing(fn (Builder $query) => $this->filterInbox($query, 'submissions')),

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
