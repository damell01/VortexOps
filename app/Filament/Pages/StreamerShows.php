<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\RespectsRoleVisibility;
use App\Models\Show;
use App\Models\Streamer;
use App\Support\NavVisibility;
use Filament\Pages\Page;

class StreamerShows extends Page
{
    use RespectsRoleVisibility;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-home';
    protected static ?string $navigationLabel = 'My Shows';
    protected static ?int $navigationSort = 1;

    private ?array $groupsMemo = null;

    public function getTitle(): string
    {
        return 'My Shows';
    }

    /** The welcome banner is the page's heading; no second title above it. */
    public function getHeading(): string|\Illuminate\Contracts\Support\Htmlable|null
    {
        return null;
    }

    public function getSubheading(): ?string
    {
        // The page's own summary card says what is waiting; a second line of
        // header copy above it only pushed the cards further down on phones.
        return null;
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Streamer';
    }

    public static function shouldRegisterNavigation(): bool
    {
        if (NavVisibility::isHiddenForUser(static::class, auth()->user())) {
            return false;
        }

        return (bool) auth()->user()?->streamer
            || \App\Support\NavVisibility::isExplicitlyGrantedTo(static::class, auth()->user());
    }

    public function mount(): void
    {
        abort_unless((bool) auth()->user()?->streamer, 403);
    }

    public function getView(): string
    {
        return 'filament.pages.streamer-shows';
    }

    public function getStreamer(): Streamer
    {
        return auth()->user()?->streamer ?? abort(403);
    }

    public static function getNavigationBadge(): ?string
    {
        if (! auth()->user()?->streamer) {
            return null;
        }

        $count = count((new static())->groups()['needs_you']);

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public function quickSummary(): array
    {
        $groups = $this->groups();

        return [
            'needs_you' => count($groups['needs_you']),
            'upcoming' => count($groups['upcoming']),
            'submitted' => count($groups['waiting']),
            'approved' => count($groups['done']),
        ];
    }

    public function groups(): array
    {
        if ($this->groupsMemo !== null) {
            return $this->groupsMemo;
        }

        $streamer = auth()->user()?->streamer;

        if (! $streamer) {
            return $this->groupsMemo = ['needs_you' => [], 'waiting' => [], 'upcoming' => [], 'done' => []];
        }

        $shows = Show::query()
            ->whereHas('streamers', fn ($q) => $q->where('streamers.id', $streamer->id))
            ->where('status', '!=', 'cancelled')
            // Shows before reporting went live were handled the old way; they
            // stay on the calendar but never appear here asking for a report.
            ->tap(fn ($q) => \App\Support\ShowReportingGoLive::scope($q))
            ->with(['channel', 'streamerLogEntry'])
            ->withCount('shipments')
            ->orderByDesc('show_date')
            ->orderByDesc('start_time')
            ->limit(60)
            ->get();

        $groups = ['needs_you' => [], 'waiting' => [], 'upcoming' => [], 'done' => []];

        foreach ($shows as $show) {
            $groups[$this->bucketFor($show)][] = $this->cardFor($show);
        }

        $groups['upcoming'] = array_reverse($groups['upcoming']);

        return $this->groupsMemo = $groups;
    }

    private function bucketFor(Show $show): string
    {
        if ($show->show_date && $show->show_date->isFuture()) {
            return 'upcoming';
        }

        $entry = $show->streamerLogEntry;

        if (! $entry || ! $entry->isSubmitted()) {
            return 'needs_you';
        }

        if ($entry->status === 'changes_requested') {
            return 'needs_you';
        }

        return $entry->status === 'admin_approved' ? 'done' : 'waiting';
    }

    private function cardFor(Show $show): array
    {
        $entry   = $show->streamerLogEntry;
        $isDraft = $entry && ! $entry->isSubmitted();

        [$state, $tone, $action] = match (true) {
            $show->show_date && $show->show_date->isFuture()
                => ['Scheduled', 'gray', null],
            $entry && $entry->status === 'changes_requested'
                => ['Changes requested', 'danger', 'Review changes'],
            ! $entry
                => ['Needs items logged', 'warning', 'Open show'],
            $isDraft
                => ['Draft saved', 'warning', 'Open show'],
            $entry->hasPendingRevisionRequest()
                => ['Changes asked for', 'info', 'Open show'],
            $entry->status === 'admin_approved'
                => ['Approved', 'success', 'Open show'],
            default
                => ['Submitted', 'info', 'Open show'],
        };

        return [
            'id'        => $show->id,
            'title'     => $show->title ?? 'Show #' . $show->id,
            'date'      => $show->show_date?->format('D, M j'),
            'time'      => $show->start_time,
            'channel'   => $show->channel?->name,
            'state'     => $state,
            'tone'      => $tone,
            'action'    => $action,
            'url'       => $action
                ? EndOfStreamForm::getUrl(['showId' => $show->id])
                : null,
            'day'       => $show->show_date ? (int) floor($show->show_date->timestamp / 86400) : 0,
            'shipments' => (int) ($show->shipments_count ?? 0),
            'slow_pack' => (bool) $show->is_slow_pack,
            'can_request_revision' => (bool) $entry?->canRequestRevision(),
            'revision_requested'   => (bool) $entry?->hasPendingRevisionRequest(),
            'revision_reason'      => $entry?->revision_reason,
        ];
    }

    // ── Your numbers ────────────────────────────────────────────────────────

    public string $period = 'month';

    /** @return array{0:\Illuminate\Support\Carbon,1:\Illuminate\Support\Carbon,2:string} */
    public function periodRange(): array
    {
        $now = \App\Support\BusinessTime::local();

        return match ($this->period) {
            'last_month' => [$now->copy()->subMonthNoOverflow()->startOfMonth(), $now->copy()->subMonthNoOverflow()->endOfMonth(), $now->copy()->subMonthNoOverflow()->format('F Y')],
            'year' => [$now->copy()->startOfYear(), $now->copy()->endOfYear(), (string) $now->year],
            'last_year' => [$now->copy()->subYear()->startOfYear(), $now->copy()->subYear()->endOfYear(), (string) ($now->year - 1)],
            default => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth(), $now->format('F Y')],
        };
    }

    public function setPeriod(string $period): void
    {
        $this->period = in_array($period, ['month', 'last_month', 'year', 'last_year'], true) ? $period : 'month';
    }

    /** The period just before the chosen one, for "vs last month". @return array{0:\Illuminate\Support\Carbon,1:\Illuminate\Support\Carbon} */
    public function previousRange(): array
    {
        [$from, $to] = $this->periodRange();

        return in_array($this->period, ['year', 'last_year'], true)
            ? [$from->copy()->subYear(), $to->copy()->subYear()]
            : [$from->copy()->subMonthNoOverflow()->startOfMonth(), $from->copy()->subMonthNoOverflow()->endOfMonth()];
    }

    /** Numbers for the chosen period, the one before, and the daily sales series. */
    public function overview(): array
    {
        [$from, $to, $label] = $this->periodRange();
        [$pFrom, $pTo] = $this->previousRange();
        $streamer = $this->getStreamer();

        return [
            'label' => $label,
            'now' => \App\Support\StreamerNumbers::for($streamer, $from, $to),
            'before' => \App\Support\StreamerNumbers::for($streamer, $pFrom, $pTo),
            'series' => \App\Support\StreamerNumbers::series($streamer, $from, $to),
            'compare' => in_array($this->period, ['year', 'last_year'], true) ? 'last year' : 'last month',
        ];
    }

    /** The next few shows, soonest first. */
    public function nextShows(int $limit = 4): array
    {
        return array_slice($this->groups()['upcoming'], 0, $limit);
    }

    public function firstName(): string
    {
        return \Illuminate\Support\Str::of(auth()->user()?->name ?? '')->before(' ')->toString() ?: 'there';
    }

    public function numbers(): array
    {
        [$from, $to] = $this->periodRange();

        return \App\Support\StreamerNumbers::for($this->getStreamer(), $from, $to);
    }

    // ── Every show, searchable ──────────────────────────────────────────────

    public string $search = '';
    public string $channelFilter = '';
    public string $when = 'all';
    public int $listPage = 1;

    public function updatedSearch(): void { $this->listPage = 1; }
    public function updatedChannelFilter(): void { $this->listPage = 1; }
    public function updatedWhen(): void { $this->listPage = 1; }
    public function gotoListPage(int $page): void { $this->listPage = max(1, $page); }

    public function allShows()
    {
        $streamer = $this->getStreamer();
        $today = \App\Support\BusinessTime::local()->toDateString();

        return Show::query()
            ->whereHas('streamers', fn ($q) => $q->where('streamers.id', $streamer->id))
            ->when(trim($this->search) !== '', fn ($q) => $q->where('title', 'like', '%'.trim($this->search).'%'))
            ->when($this->channelFilter !== '', fn ($q) => $q->where('whatnot_channel_id', (int) $this->channelFilter))
            ->when($this->when === 'upcoming', fn ($q) => $q->whereDate('show_date', '>=', $today)->where('status', '!=', 'cancelled'))
            ->when($this->when === 'past', fn ($q) => $q->whereDate('show_date', '<', $today))
            ->when($this->when === 'month', fn ($q) => $q->whereBetween('show_date', [substr($today, 0, 7).'-01', $today.' 23:59:59']))
            ->when($this->when === 'year', fn ($q) => $q->whereBetween('show_date', [substr($today, 0, 4).'-01-01', substr($today, 0, 4).'-12-31 23:59:59']))
            ->with([
                'channel:id,name',
                'streamerLogEntry:id,show_id,status,submitted_at,hours_streamed',
                'payouts' => fn ($q) => $q->where('streamer_id', $streamer->id)->select('id', 'show_id', 'streamer_id', 'status', 'calculated_payout'),
            ])
            ->orderByDesc('show_date')->orderByDesc('start_time')
            ->paginate(20, ['id', 'title', 'show_date', 'start_time', 'status', 'whatnot_channel_id', 'gross_revenue', 'units_sold'], 'p', $this->listPage);
    }

    public function myChannels(): array
    {
        $streamer = $this->getStreamer();

        return \App\Models\WhatnotChannel::whereIn('id', Show::whereHas('streamers', fn ($q) => $q->where('streamers.id', $streamer->id))->select('whatnot_channel_id'))
            ->orderBy('name')->pluck('name', 'id')->all();
    }

    public ?int $revisionFor = null;
    public string $revisionReason = '';

    public function askForChanges(int $showId): void
    {
        $this->revisionFor    = $showId;
        $this->revisionReason = '';
    }

    public function cancelRevisionRequest(): void
    {
        $this->revisionFor    = null;
        $this->revisionReason = '';
    }

    public function submitRevisionRequest(): void
    {
        $show = $this->revisionFor
            ? Show::with('streamerLogEntry')->find($this->revisionFor)
            : null;

        $entry = $show?->streamerLogEntry;

        if (! $entry || ! $entry->canRequestRevision() || ! $this->ownsShow($show)) {
            $this->cancelRevisionRequest();
            return;
        }

        $entry->requestRevision($this->revisionReason);
        $this->notifyAdminsOfRevisionRequest($show, $entry);

        $this->groupsMemo = null;
        $this->cancelRevisionRequest();

        \Filament\Notifications\Notification::make()
            ->title('Asked for changes')
            ->body('An admin has been told. The report stays as filed until they reopen it.')
            ->success()
            ->send();
    }

    private function ownsShow(Show $show): bool
    {
        $streamerId = auth()->user()?->streamer?->id;
        return $streamerId !== null && $show->streamers->contains('id', $streamerId);
    }

    private function notifyAdminsOfRevisionRequest(Show $show, \App\Models\StreamerLogEntry $entry): void
    {
        \App\Services\Notifier::send('report_reopen_requested', new \App\Notifications\VortexAlert(
            event: 'report_reopen_requested',
            title: 'A streamer wants to change a filed report',
            body: trim(($show->title ?? 'Show #' . $show->id) . ' — ' . ($entry->revision_reason ?: 'no reason given')),
            tone: 'warning',
            links: ['Open the report' => \App\Filament\Resources\StreamerLogResource::getUrl('index')],
        ));
    }
}
