<?php

namespace App\Filament\Pages;

use App\Filament\Resources\ShowResource;
use App\Models\Show;
use App\Models\Streamer;
use App\Models\WhatnotChannel;
use App\Support\AdminModules;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Notifications\Notification;
use Filament\Panel;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Support\Enums\Width;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;

/**
 * Shows Overview: a scheduling calendar, not a report.
 *
 * Month, Week, List and Day are four renderings of one data set — the shows in
 * the month grid around $anchor — so switching views or clicking a show never
 * refetches. Each show is reduced once by present() into the array that both
 * the Blade views and the drawer/mobile agenda (Alpine, from JSON) read.
 * Performance reporting stays on Reports; this page answers what is happening,
 * when, and who is responsible.
 */
class Shows extends Page
{
    /** Secondary workspace tool: opened from its hub; direct access/permissions stay unchanged. */
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    use \App\Filament\Concerns\HasAdminNavVisibility;

    protected static string $moduleSlug = 'streams';
    protected static ?string $title = 'Shows';

    public const VIEWS = ['month', 'week', 'list', 'day'];

    /** Shows starting before this hour are treated as the tail of the previous evening. */
    private const LATE_NIGHT_CUTOFF = 5;

    /** Channel colours, assigned by channel id so each channel keeps its colour. */
    private const PALETTE = ['#ef4444', '#3b82f6', '#8b5cf6', '#10b981', '#f59e0b', '#ec4899', '#06b6d4', '#84cc16'];

    #[Url(as: 'view')] public string $viewMode = 'month';
    #[Url(as: 'date')] public string $anchor = '';
    #[Url(as: 'status')] public string $filterStatus = 'all';
    #[Url(as: 'streamer')] public string $filterStreamer = '';
    #[Url(as: 'channel')] public string $filterChannel = '';
    #[Url(as: 'q')] public string $searchQuery = '';

    public function mount(): void
    {
        // Older links (Streams overview, bookmarks) pass a from/to period.
        if ($this->anchor === '' && ($from = request()->query('from'))) $this->anchor = (string) $from;
        $this->anchor = $this->anchorDate()->toDateString();
        if (! in_array($this->viewMode, self::VIEWS, true)) $this->viewMode = 'month';
    }

    public function getView(): string { return 'filament.pages.shows'; }
    public function getSubheading(): ?string { return 'Schedule, assign and review your Whatnot shows.'; }
    public static function getNavigationIcon(): string { return 'heroicon-o-presentation-chart-line'; }
    public static function getNavigationGroup(): ?string { return AdminModules::navigationGroupFor('streams'); }
    public static function getNavigationSort(): ?int { return 20; }
    public static function getNavigationLabel(): string { return 'Shows'; }
    public static function getSlug(?Panel $panel = null): string { return 'shows-overview'; }

    public static function canAccess(): bool
    {
        if (\App\Support\RoleAccess::grants(static::class)) return true;
        $u = auth()->user();
        return AdminModules::isEnabled('streams') && ($u?->isAdmin() || $u?->isStreamer());
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('show_performance')
                ->label('Show Performance')
                ->icon('heroicon-o-chart-bar')
                ->color('gray')
                ->visible(fn () => Reports::canAccess())
                ->url(fn () => Reports::getUrl()),
            Action::make('unassigned')
                ->label('Unassigned')
                ->icon('heroicon-o-user-plus')
                ->color('gray')
                ->badge(fn () => $this->unassignedShows->count() ?: null)
                ->badgeColor('danger')
                ->visible(fn () => auth()->user()?->isAdmin() ?? false)
                ->slideOver()
                ->modalWidth(Width::Medium)
                ->modalHeading('Unassigned shows')
                ->modalDescription(fn () => 'Shows in '.$this->anchorDate()->format('F Y').' that still need a streamer.')
                ->modalContent(fn () => view('filament.pages.partials.shows-unassigned', ['page' => $this]))
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Done'),
            $this->addShowAction(),
        ];
    }

    /** Opened from the header, or from an empty calendar cell with that day already chosen. */
    public function addShowAction(): Action
    {
        return Action::make('addShow')
            ->label('Add Show')
            ->icon('heroicon-o-plus')
            ->color('primary')
            ->visible(fn () => auth()->user()?->isAdmin() ?? false)
            ->slideOver()
            ->modalWidth(Width::Large)
            ->modalHeading('Add show')
            ->modalSubmitActionLabel('Add show')
            ->fillForm(fn (array $arguments) => [
                'show_date' => $this->parseDate($arguments['date'] ?? null)?->toDateString() ?? $this->anchorDate()->toDateString(),
                // A click on the week grid passes the half-hour it landed on.
                'start_time' => is_string($t = $arguments['time'] ?? null) && preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $t) ? $t : null,
            ])
            ->schema([
                Grid::make(2)->schema([
                    DatePicker::make('show_date')->label('Show date')->required()->columnSpanFull(),
                    TimePicker::make('start_time')->label('Start time')->seconds(false),
                    TimePicker::make('end_time')->label('End time')->seconds(false),
                    TextInput::make('title')->label('Show title')->placeholder('e.g. Vortex Cards — Friday Night Breaks')->maxLength(255)->columnSpanFull(),
                    Select::make('whatnot_channel_id')->label('Channel')->options(fn () => WhatnotChannel::where('status', 'active')->orderBy('name')->pluck('name', 'id'))->searchable()->nullable()->columnSpanFull(),
                    Select::make('streamers')->label('Streamers')->multiple()->options(fn () => Streamer::where('status', 'active')->orderBy('name')->pluck('name', 'id'))->searchable()->columnSpanFull(),
                ]),
            ])
            ->action(function (array $data): void {
                abort_unless(auth()->user()?->isAdmin(), 403);
                $show = Show::create([
                    'show_date' => $data['show_date'],
                    'start_time' => $data['start_time'] ?? null,
                    'end_time' => $data['end_time'] ?? null,
                    'title' => filled($data['title'] ?? null) ? $data['title'] : 'Show on '.Carbon::parse($data['show_date'])->format('M d, Y'),
                    'whatnot_channel_id' => $data['whatnot_channel_id'] ?? null,
                    'status' => 'draft',
                    'import_source' => 'manual',
                    'created_by' => auth()->id(),
                ]);
                if (! empty($data['streamers'])) $show->streamers()->attach($data['streamers']);
                $this->anchor = $show->show_date->toDateString();
                $this->resetData();
                Notification::make()->title('Show added')->body("'{$show->title}' is on the calendar for {$show->show_date->format('M j, Y')}.")->success()->send();
            });
    }

    // ── Navigation ──────────────────────────────────────────────────────────

    public function setView(string $view): void
    {
        if (in_array($view, self::VIEWS, true)) $this->viewMode = $view;
    }

    public function openDay(string $date): void
    {
        $this->goToDate($date);
        $this->viewMode = 'day';
    }

    /** Moves the calendar; the loaded range follows the anchor's month grid. */
    public function goToDate(string $date): void
    {
        if ($d = $this->parseDate($date)) $this->anchor = $d->toDateString();
        $this->resetData();
    }

    public function goToToday(): void { $this->goToDate(today()->toDateString()); }
    public function goPrevious(): void { $this->shift(-1); }
    public function goNext(): void { $this->shift(1); }

    private function shift(int $dir): void
    {
        $a = $this->anchorDate();
        $a = match ($this->viewMode) {
            'week' => $a->addWeeks($dir),
            'day' => $a->addDays($dir),
            default => $a->addMonthsNoOverflow($dir),
        };
        $this->goToDate($a->toDateString());
    }

    public function anchorDate(): Carbon
    {
        return $this->parseDate($this->anchor) ?? today();
    }

    private function parseDate(?string $value): ?Carbon
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) return null;
        try { return Carbon::createFromFormat('!Y-m-d', $value); } catch (\Throwable) { return null; }
    }

    /** First/last day of the 6-row-or-fewer Sunday-first grid around the anchor's month. */
    public function gridRange(): array
    {
        $a = $this->anchorDate();
        return [
            $a->copy()->startOfMonth()->startOfWeek(Carbon::SUNDAY),
            $a->copy()->endOfMonth()->endOfWeek(Carbon::SATURDAY)->startOfDay(),
        ];
    }

    public function weekRange(): array
    {
        $start = $this->anchorDate()->startOfWeek(Carbon::SUNDAY);
        return [$start, $start->copy()->addDays(6)];
    }

    public function calendarTitle(): string
    {
        $a = $this->anchorDate();
        return match ($this->viewMode) {
            'week' => (function () {
                [$s, $e] = $this->weekRange();
                return $s->isSameMonth($e) ? $s->format('M j').' – '.$e->format('j, Y') : $s->format('M j').' – '.$e->format('M j, Y');
            })(),
            'day' => $a->format('l, F j, Y'),
            default => $a->format('F Y'),
        };
    }

    // ── Filters ─────────────────────────────────────────────────────────────

    public function updatedFilterStatus(): void { $this->resetData(); }
    public function updatedFilterStreamer(): void { $this->resetData(); }
    public function updatedFilterChannel(): void { $this->resetData(); }
    public function updatedSearchQuery(): void { $this->resetData(); }

    public function activeFilterCount(): int
    {
        return (int) ($this->filterStatus !== 'all') + (int) ($this->filterStreamer !== '') + (int) ($this->filterChannel !== '') + (int) (trim($this->searchQuery) !== '');
    }

    public function clearFilters(): void
    {
        $this->filterStatus = 'all';
        $this->filterStreamer = '';
        $this->filterChannel = '';
        $this->searchQuery = '';
        $this->resetData();
    }

    private function resetData(): void
    {
        unset($this->shows, $this->presented, $this->unassignedShows, $this->monthStats);
    }

    // ── Data ────────────────────────────────────────────────────────────────

    #[Computed] public function streamers(): Collection
    {
        $u = auth()->user();
        if ($u?->isAdmin()) return Streamer::orderBy('name')->get();
        return $u?->streamer ? collect([$u->streamer]) : collect();
    }

    #[Computed] public function channels(): Collection
    {
        return WhatnotChannel::query()->orderBy('name')->get(['id', 'name', 'display_title', 'status']);
    }

    protected function scopedQuery()
    {
        $u = auth()->user();
        $q = Show::query()->inChannelContext();
        if ($u?->isStreamer() && ! $u?->isAdmin()) {
            $id = $u->streamer?->id;
            $q->whereHas('streamers', fn ($x) => $x->where('streamers.id', $id));
        }
        return $q;
    }

    /** Every show in the visible month grid, filters applied. */
    #[Computed] public function shows(): Collection
    {
        [$from, $to] = $this->gridRange();
        $q = $this->scopedQuery()->whereBetween('show_date', [$from->toDateString(), $to->toDateString()]);
        if ($this->filterStatus !== 'all') $q->where('status', $this->filterStatus);
        if ($this->filterStreamer !== '' && auth()->user()?->isAdmin()) $q->whereHas('streamers', fn ($x) => $x->where('streamers.id', (int) $this->filterStreamer));
        if ($this->filterChannel !== '') $q->where('whatnot_channel_id', (int) $this->filterChannel);
        if (($n = trim($this->searchQuery)) !== '') {
            $q->where(fn ($x) => $x->where('title', 'like', "%{$n}%")->orWhere('notes', 'like', "%{$n}%")->orWhere('whatnot_show_id', 'like', "%{$n}%"));
        }

        return $q->with(['streamers:id,name', 'streamerLogEntry', 'channel:id,name,display_title'])
            ->withCount(['orders', 'deductionRequests'])
            ->orderBy('show_date')->orderBy('start_time')
            ->limit(500)->get();
    }

    /** @return Collection<int, array> presented shows, in calendar order */
    #[Computed] public function presented(): Collection
    {
        $now = now(auth()->user()?->timezone ?: config('app.timezone'));
        return $this->shows->map(fn (Show $s) => $this->present($s, $now))
            ->sortBy(fn ($p) => $p['date'].sprintf('%05d', $p['sort']))->values();
    }

    /** @return Collection<string, Collection> presented shows keyed by Y-m-d */
    public function byDate(): Collection
    {
        return $this->presented->groupBy('date');
    }

    public function present(Show $s, Carbon $now): array
    {
        $date = $s->show_date?->toDateString() ?? '';
        [$startMin, $endMin] = $this->minutes($s);
        $channel = $s->channel;
        $color = $channel ? self::PALETTE[$channel->id % count(self::PALETTE)] : '#94a3b8';
        $state = $this->scheduleState($s, $startMin, $endMin, $now);
        $log = $s->streamerLogEntry;
        $coverage = $s->analyticsCoverageStatus();

        return [
            'id' => $s->id,
            'date' => $date,
            'dateLabel' => $s->show_date?->format('D, M j') ?? '',
            'sort' => $startMin ?? 9999,
            'start' => $startMin,
            'end' => $endMin,
            'time' => $s->start_time?->format('g:i A') ?? 'Time TBD',
            'timeRange' => $this->timeRange($s),
            'duration' => $startMin !== null && $endMin !== null ? $this->durationLabel($endMin - $startMin) : null,
            'title' => (string) ($s->title ?: 'Untitled show'),
            'channel' => $channel?->display_title ?: $channel?->name,
            'color' => $color,
            'streamers' => $s->streamers->pluck('name')->join(', ') ?: null,
            'initial' => mb_strtoupper(mb_substr($s->streamers->first()?->name ?? '?', 0, 1)),
            'state' => $state,
            'stateLabel' => ['live' => 'Live', 'upcoming' => 'Upcoming', 'done' => 'Ended', 'cancelled' => 'Cancelled'][$state],
            'workflow' => $this->workflowLabel($s),
            'gross' => '$'.number_format((float) $s->gross_revenue, 0),
            'net' => '$'.number_format((float) $s->whatnot_net, 0),
            'units' => number_format((int) $s->units_sold),
            'views' => number_format((int) $s->total_views),
            'cover' => $s->cover_image_url,
            'whatnotId' => $s->whatnot_show_id,
            'analyticsChecked' => auth()->user()?->isOwner() ? (data_get($s->raw_import_payload, '_analytics_last_attempt_at') ?? $s->last_analytics_synced_at?->toIso8601String() ?? $s->analytics_unavailable_at?->toIso8601String()) : null,
            'analyticsNote' => auth()->user()?->isOwner() ? $s->analytics_sync_note : null,
            'analytics' => auth()->user()?->isOwner() ? (['complete' => 'Synced', 'partial' => 'Partial · retry pending', 'unavailable' => 'Waiting for analytics', 'unclassified' => $state === 'upcoming' ? 'After show' : 'Waiting for analytics'][$coverage] ?? 'Waiting for analytics') : null,
            'analyticsOk' => auth()->user()?->isOwner() && $coverage === 'complete',
            'orders' => $s->orders_count > 0 ? 'Synced · '.number_format($s->orders_count) : ($state === 'upcoming' ? 'After show' : 'None yet'),
            'ordersOk' => $s->orders_count > 0,
            'inventory' => ($log?->submitted_at || $s->deduction_requests_count > 0) ? 'Linked' : 'Not linked',
            'inventoryOk' => (bool) ($log?->submitted_at || $s->deduction_requests_count > 0),
            'viewUrl' => $this->showUrl($s->id),
            'editUrl' => ShowResource::canEdit($s) ? $this->editUrl($s->id) : null,
            'whatnotUrl' => filter_var($s->detail_url, FILTER_VALIDATE_URL) ? $s->detail_url : null,
        ];
    }

    /**
     * Start/end as minutes from the show date's midnight. A start before 5 AM is
     * the late slot of that evening (a 12:00 AM show follows the 10:30 PM one), and
     * an end earlier than the start rolls past midnight.
     */
    private function minutes(Show $s): array
    {
        if (! $s->start_time) return [null, null];
        $start = $s->start_time->hour * 60 + $s->start_time->minute;
        if ($s->start_time->hour < self::LATE_NIGHT_CUTOFF) $start += 1440;
        if ($s->end_time) {
            $end = $s->end_time->hour * 60 + $s->end_time->minute + ($start >= 1440 ? 1440 : 0);
            while ($end <= $start) $end += 1440;
        } elseif ($s->show_duration > 0) {
            // show_duration is stored in minutes by the importer and the form.
            $end = $start + (int) $s->show_duration;
        } else {
            $end = null;
        }
        return [$start, $end];
    }

    private function scheduleState(Show $s, ?int $startMin, ?int $endMin, Carbon $now): string
    {
        if ($s->status === 'cancelled') return 'cancelled';
        if (! $s->show_date) return 'upcoming';
        $day = Carbon::parse($s->show_date->toDateString(), $now->getTimezone());
        if ($startMin === null) return $day->isAfter($now->copy()->startOfDay()) || $day->isSameDay($now) ? 'upcoming' : 'done';
        $start = $day->copy()->addMinutes($startMin);
        $end = $day->copy()->addMinutes($endMin ?? $startMin + 180);
        if ($now->lt($start)) return 'upcoming';
        return $now->lte($end) ? 'live' : 'done';
    }

    private function timeRange(Show $s): string
    {
        if (! $s->start_time) return 'Time TBD';
        return $s->end_time ? $s->start_time->format('g:i A').' – '.$s->end_time->format('g:i A') : $s->start_time->format('g:i A');
    }

    private function durationLabel(int $minutes): string
    {
        $h = intdiv($minutes, 60);
        $m = $minutes % 60;
        return trim(($h ? $h.'h ' : '').($m ? $m.'m' : ''));
    }

    /** Month cells, Sunday first: [['date' => Carbon, 'inMonth' => bool, 'shows' => Collection]] per week. */
    public function monthWeeks(): Collection
    {
        [$from, $to] = $this->gridRange();
        $byDate = $this->byDate();
        $month = $this->anchorDate()->month;
        $weeks = collect();
        for ($cursor = $from->copy(); $cursor->lte($to); $cursor->addWeek()) {
            $weeks->push(collect(range(0, 6))->map(function ($i) use ($cursor, $byDate, $month) {
                $d = $cursor->copy()->addDays($i);
                return ['date' => $d, 'inMonth' => $d->month === $month, 'shows' => $byDate->get($d->toDateString(), collect())];
            }));
        }
        return $weeks;
    }

    /**
     * Week time grid. Hours span the week's earliest start to latest end (at
     * least noon–11 PM); overlapping shows in a day split into side-by-side lanes.
     */
    public function weekGrid(): array
    {
        [$start] = $this->weekRange();
        $byDate = $this->byDate();
        $days = collect(range(0, 6))->map(fn ($i) => $start->copy()->addDays($i));
        $timed = $days->flatMap(fn ($d) => $byDate->get($d->toDateString(), collect())->whereNotNull('start'));
        $first = min(12, intdiv((int) ($timed->min('start') ?? 720), 60));
        $last = max(23, (int) ceil(($timed->map(fn ($p) => $p['end'] ?? $p['start'] + 90)->max() ?? 1380) / 60));
        $last = min($last, $first + 30);

        $columns = $days->map(function (Carbon $d) use ($byDate, $first) {
            $shows = $byDate->get($d->toDateString(), collect());
            // Lanes are counted per cluster of overlapping shows, so a show
            // that overlaps nothing keeps the full column width.
            $placed = [];
            $cluster = [];
            $lanesEnd = [];
            $clusterEnd = -1;
            $flush = function () use (&$placed, &$cluster, &$lanesEnd) {
                foreach ($cluster as $p) $placed[] = $p + ['lanes' => max(1, count($lanesEnd))];
                $cluster = [];
                $lanesEnd = [];
            };
            foreach ($shows->whereNotNull('start')->values() as $p) {
                $end = max($p['end'] ?? $p['start'] + 90, $p['start'] + 30);
                if ($p['start'] >= $clusterEnd) $flush();
                $lane = 0;
                while (isset($lanesEnd[$lane]) && $lanesEnd[$lane] > $p['start']) $lane++;
                $lanesEnd[$lane] = $end;
                $clusterEnd = max($clusterEnd, $end);
                $cluster[] = $p + ['top' => $p['start'] - $first * 60, 'height' => $end - $p['start'], 'lane' => $lane];
            }
            $flush();
            return ['date' => $d, 'timed' => collect($placed), 'untimed' => $shows->whereNull('start')->values()];
        });

        return ['first' => $first, 'last' => $last, 'columns' => $columns, 'hasUntimed' => $columns->contains(fn ($c) => $c['untimed']->isNotEmpty())];
    }

    /** List view: the anchor's month, as an agenda grouped by day. */
    public function agendaDays(): Collection
    {
        $a = $this->anchorDate();
        return $this->byDate()->filter(fn ($shows, $date) => str_starts_with($date, $a->format('Y-m')));
    }

    public function dayShows(): Collection
    {
        return $this->byDate()->get($this->anchorDate()->toDateString(), collect());
    }

    /** Desktop KPI cards for the anchor's month. */
    #[Computed] public function monthStats(): array
    {
        $a = $this->anchorDate();
        $month = $this->shows->filter(fn (Show $s) => $s->show_date?->isSameMonth($a));
        $total = $month->count();
        $completed = $month->filter(fn (Show $s) => $this->workflowLabel($s) === 'Completed')->count();
        return [
            'label' => $a->copy()->startOfMonth()->format('M j').' – '.$a->copy()->endOfMonth()->format('M j, Y'),
            'total' => $total,
            'completed' => $completed,
            'completedPct' => $total ? (int) round($completed / $total * 100) : 0,
            'gross' => (float) $month->sum('gross_revenue'),
            'net' => (float) $month->sum('whatnot_net'),
        ];
    }

    /** Client payload for the drawer and the mobile agenda. */
    public function calendarPayload(): array
    {
        [$from, $to] = $this->gridRange();
        return [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'today' => today()->toDateString(),
            'anchor' => $this->anchorDate()->toDateString(),
            'canAdd' => (bool) auth()->user()?->isAdmin(),
            'shows' => $this->presented->keyBy('id'),
        ];
    }

    public function channelLegend(): Collection
    {
        return $this->presented->filter(fn ($p) => $p['channel'])->unique('channel')->map(fn ($p) => ['name' => $p['channel'], 'color' => $p['color']])->sortBy('name')->values();
    }

    // ── Assignment & workflow (unchanged behaviour) ─────────────────────────

    public function workflowLabel(Show $s): string
    {
        $l = $s->streamerLogEntry;
        if (! $this->isShowDue($s)) return 'Scheduled';
        if (! $l) return 'Report needed';
        if ($l->status === 'changes_requested' || $l->approval_status === 'rejected') return 'Changes requested';
        if (! $l->isSubmitted()) return 'Draft report';
        if (! $l->reviewed_at) return 'Admin review';
        if ($l->needsFulfillmentReview() && ! $l->fulfillment_reviewed_at) return 'Fulfillment review';
        return 'Completed';
    }

    #[Computed] public function unassignedShows(): Collection
    {
        if (! auth()->user()?->isAdmin()) return collect();
        $a = $this->anchorDate();
        return Show::query()->inChannelContext()
            ->whereBetween('show_date', [$a->copy()->startOfMonth()->toDateString(), $a->copy()->endOfMonth()->toDateString()])
            ->whereDoesntHave('streamers')
            ->whereNotIn('status', ['cancelled', 'closed'])
            ->orderBy('show_date')->orderBy('start_time')->limit(100)->get();
    }

    public array $selectedUnassignedShows = [];
    public string $bulkStreamerId = '';

    public function assignSelectedShows(): void
    {
        abort_unless(auth()->user()?->isAdmin(), 403);
        $this->validate(['selectedUnassignedShows' => ['required', 'array', 'min:1', 'max:100'], 'selectedUnassignedShows.*' => ['integer'], 'bulkStreamerId' => ['required', 'integer']]);
        $streamer = Streamer::query()->inChannelContext()->where('status', 'active')->findOrFail((int) $this->bulkStreamerId);
        $eligible = $this->unassignedShows->pluck('id')->all();
        $ids = array_values(array_unique(array_map('intval', $this->selectedUnassignedShows)));
        abort_if(count(array_diff($ids, $eligible)) > 0, 422, 'The selection changed. Refresh and select the unassigned shows again.');
        $count = DB::transaction(function () use ($ids, $streamer) {
            $shows = Show::query()->inChannelContext()->whereKey($ids)->lockForUpdate()->get();
            foreach ($shows as $show) {
                abort_if($show->streamers()->exists(), 422, 'A selected show has already been assigned. Refresh the selection.');
                $show->streamers()->sync([$streamer->id => ['is_primary' => true]]);
            }
            return $shows->count();
        });
        $this->selectedUnassignedShows = [];
        $this->bulkStreamerId = '';
        $this->resetData();
        Notification::make()->title($count.' shows assigned')->body('Assigned to '.$streamer->name)->success()->send();
    }

    public function retryShowAnalytics(int $showId): void
    {
        abort_unless(auth()->user()?->isOwner(), 403);
        $show = Show::query()->inChannelContext()->readyForAnalytics()->findOrFail($showId);
        if (! $show->whatnot_show_id || ! $show->whatnot_channel_id) {
            Notification::make()->title('This show needs its Whatnot ID and channel before retrying')->warning()->send();
            return;
        }
        \App\Jobs\RetryShowAnalytics::dispatch($show->id);
        Notification::make()->title('Analytics retry queued')->body('It will wait for the active scraper. This does not mean the metrics have synced yet.')->success()->send();
    }

    public function assignStreamer(int $showId, int $streamerId): void
    {
        abort_unless(auth()->user()?->isAdmin(), 403);
        $show = Show::query()->inChannelContext()->findOrFail($showId);
        $streamer = Streamer::query()->inChannelContext()->where('status', 'active')->findOrFail($streamerId);
        $show->streamers()->sync([$streamer->id => ['is_primary' => true]]);
        Notification::make()->title('Streamer assigned')->body($streamer->name.' → '.$show->title)->success()->send();
        $this->resetData();
    }

    public function showUrl(int $id): string { return ShowResource::getUrl('view', ['record' => $id]); }
    public function editUrl(int $id): string { return ShowResource::getUrl('edit', ['record' => $id]); }

    public function isShowDue(Show $s): bool
    {
        if ($s->show_date?->isFuture()) return false;
        if ($s->show_date?->isToday() && $s->start_time && $s->start_time->isFuture()) return false;
        return true;
    }

    public function requestFormSubmission($id): void
    {
        $s = Show::with('streamers.user')->findOrFail((int) $id);
        if (! $this->isShowDue($s)) { Notification::make()->title('Show has not happened yet')->warning()->send(); return; }
        \App\Services\Notifier::send('report_submission_requested', new \App\Notifications\VortexAlert(
            event: 'report_submission_requested',
            title: 'Show report requested',
            body: "Please submit the end-of-stream report for \"{$s->title}\".",
            tone: 'warning',
            links: ['Open my shows' => StreamerShows::getUrl()],
        ), $s->streamers->pluck('user'));
        Notification::make()->title('Submission request sent')->success()->send();
        $this->resetData();
    }

    public function requestFormResubmission($id): void
    {
        $s = Show::findOrFail((int) $id);
        $l = $s->streamerLogEntry;
        if (! $l) { Notification::make()->title('Error')->body('No log entry found for this show')->danger()->send(); return; }
        $l->sendBackToStreamer('Admin requested changes to your submission.');
        Notification::make()->title('Change request sent')->success()->send();
        $this->resetData();
    }

    public function deleteShow(int $id): void
    {
        abort_unless(auth()->user()?->isAdmin(), 403);
        $s = Show::findOrFail($id);
        DB::transaction(function () use ($s) {
            foreach (['shipments', 'whatnot_show_orders', 'show_ingestion_logs', 'show_change_logs', 'deduction_requests', 'payouts', 'shipping_surcharges', 'show_reopening_requests', 'streamer_log_entries'] as $t) if (Schema::hasTable($t) && Schema::hasColumn($t, 'show_id')) DB::table($t)->where('show_id', $s->id)->delete();
            foreach (['show_streamer', 'show_fulfillment_user'] as $t) if (Schema::hasTable($t) && Schema::hasColumn($t, 'show_id')) DB::table($t)->where('show_id', $s->id)->delete();
            $s->delete();
        });
        Notification::make()->title('Show deleted')->success()->send();
        $this->resetData();
    }
}
