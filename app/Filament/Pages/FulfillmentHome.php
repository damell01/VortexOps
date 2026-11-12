<?php

namespace App\Filament\Pages;

use App\Models\FulfillmentRun;
use App\Models\FulfillmentShift;
use App\Models\FulfillmentStation;
use App\Models\Show;
use App\Models\TimeEntry;
use App\Models\User;
use App\Services\FulfillmentQueue;
use App\Services\FulfillmentRunService;
use App\Support\BusinessTime;
use App\Support\FulfillmentAccess;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;

/**
 * Fulfillment Home: the floor at a glance. Live stations, shows ready to pack,
 * today's work and issues. Managers assign shows from here; members see their
 * own shows and shift.
 */
class FulfillmentHome extends Page
{
    use \App\Filament\Concerns\ReviewsFulfillmentSignoff;

    /** The page draws its own header. */
    public function getHeading(): string { return ''; }

    protected static ?string $title = 'Fulfillment';
    protected static ?string $slug = 'fulfillment';
    protected static bool $shouldRegisterNavigation = false;

    /** Ready queue: all shows, long jobs (slow-pack) or the quick ones. */
    #[\Livewire\Attributes\Url(as: 'length')]
    public string $jobs = 'all';
    public string $queueSearch = '';

    public function updatedQueueSearch(): void
    {
        $this->queuePage = 1;
        unset($this->queue, $this->queueTotal, $this->queueApproved);
    }

    public function updatedJobs(): void
    {
        if (! in_array($this->jobs, ['all', 'slow', 'quick'], true)) $this->jobs = 'all';
        $this->queuePage = 1;
        unset($this->queue, $this->queueTotal);
    }

    /** The ready queue shows a page at a time, never one long list. */
    public const QUEUE_PER_PAGE = 5;

    public int $queuePage = 1;

    public function queuePageCount(): int
    {
        return max(1, (int) ceil($this->queueTotal / self::QUEUE_PER_PAGE));
    }

    /** How many shows are in the queue (for the current filter). */
    #[Computed]
    public function queueTotal(): int
    {
        return app(FulfillmentQueue::class)->queueQuery(auth()->user(), $this->jobs, $this->queueSearch)->count();
    }

    /** @deprecated the page is already sliced in the database */
    public function queueSlice(): \Illuminate\Support\Collection
    {
        return $this->queue;
    }

    public function setQueuePage(int $page): void
    {
        $this->queuePage = max(1, min($page, $this->queuePageCount()));
        unset($this->queue);
    }

    public static function canAccess(): bool
    {
        return FulfillmentAccess::moduleEnabled() && FulfillmentAccess::isMember(auth()->user());
    }

    public function getView(): string
    {
        return 'filament.pages.fulfillment-home';
    }

    public function getTitle(): string
    {
        return 'Fulfillment';
    }

    public function mount(): void
    {
        // The scheduler syncs every ten minutes; between runs, the first visit
        // in a couple of minutes picks up newly ready shows. Never on every load.
        if (\Illuminate\Support\Facades\Cache::add('fulfillment:home-sync', 1, 120)) {
            app(FulfillmentQueue::class)->sync();
        }
    }

    public function isManager(): bool
    {
        return FulfillmentAccess::isManager(auth()->user());
    }

    #[Computed]
    public function stations(): \Illuminate\Support\Collection
    {
        $stations = FulfillmentStation::query()->where('active', true)->orderBy('sort')->orderBy('id')->get();
        $runs = FulfillmentRun::query()
            ->where(fn ($q) => $q->whereIn('station_occupied_id', $stations->pluck('id'))
                ->orWhere(fn ($ready) => $ready->where('status', FulfillmentRun::READY)->whereIn('station_id', $stations->pluck('id'))))
            ->with(['show:id,title,show_date,start_time,whatnot_channel_id,cover_image_url', 'show.channel:id,name', 'show.streamers:id,name', 'packers:id,name', 'show.fulfillmentUsers:id,name'])
            ->withCount(['flags as open_flags_count' => fn ($q) => $q->where('status', 'open')])
            ->get();

        $runs->load('currentBuyer:id,letter,buyer_username');
        $buyers = $runs->isEmpty() ? collect() : DB::table('fulfillment_run_buyers')->whereIn('run_id', $runs->pluck('id'))
            ->orderBy('run_id')->orderByRaw('LENGTH(letter)')->orderBy('letter')
            ->get(['id', 'run_id', 'letter', 'buyer_username', 'status'])->groupBy('run_id');

        // Index runs once instead of scanning every run for every station.
        // Preserve the existing preference for occupied stations over ready runs.
        $occupied = $runs->filter(fn ($r) => $r->station_occupied_id !== null)->groupBy('station_occupied_id');
        $ready = $runs->filter(fn ($r) => $r->status === FulfillmentRun::READY)->groupBy('station_id');

        return $stations->map(function (FulfillmentStation $station) use ($occupied, $ready, $buyers) {
            $run = $occupied->get($station->id)?->first()
                ?? $ready->get($station->id)?->first();
            // The next buyer still waiting after the current one: what the packer picks up next.
            $next = null;
            if ($run && $run->isOnStation()) {
                $list = $buyers->get($run->id, collect())->values();
                $at = $run->current_buyer_id ? $list->search(fn ($b) => (int) $b->id === (int) $run->current_buyer_id) : -1;
                $next = $list->slice($at === false ? 0 : $at + 1)->first(fn ($b) => $b->status === 'waiting');
            }

            return ['station' => $station, 'run' => $run, 'state' => $this->stationState($run), 'next' => $next,
                'current' => $run?->isOnStation() ? $run->currentBuyer : null];
        });
    }

    /** Today's packing moments for the activity feed. */
    #[Computed]
    public function activity(): \Illuminate\Support\Collection
    {
        return \App\Models\FulfillmentEvent::query()
            ->whereIn('type', ['buyer_packed', 'item_verified', 'issue_flagged', 'issue_resolved', 'started', 'finished', 'paused', 'approved'])
            ->where('created_at', '>=', now()->subDay())
            ->with(['station:id,name', 'user:id,name'])
            ->latest('id')->limit(5)->get();
    }

    public function stationState(?FulfillmentRun $run): string
    {
        if (! $run) return 'idle';
        if ($run->isOnStation() && ($run->open_flags_count ?? 0) > 0) return 'hold';
        return $run->status;
    }

    /** The current page of the ready queue (paged in the database). */
    #[Computed]
    public function queue(): \Illuminate\Support\Collection
    {
        $this->queuePage = max(1, min($this->queuePage, $this->queuePageCount()));

        return app(FulfillmentQueue::class)->queuePage(auth()->user(), $this->jobs, $this->queuePage, self::QUEUE_PER_PAGE, $this->queueSearch);
    }

    /** Shows on this page whose streamer report is approved (one query, not one per row). */
    #[Computed]
    public function queueApproved(): array
    {
        return FulfillmentQueue::approvedReportShowIds($this->queue->pluck('show_id')->map(fn ($id) => (int) $id)->all());
    }

    #[Computed]
    public function stats(): array
    {
        // Aggregate in SQL rather than hydrating every open run on each dashboard poll.
        $open = FulfillmentRun::query()->whereIn('status', FulfillmentRun::OPEN)
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS ready_count', [FulfillmentRun::READY])
            ->selectRaw('COALESCE(SUM(expected_packages), 0) AS expected_count')
            ->selectRaw('SUM(CASE WHEN expected_packages IS NULL THEN 1 ELSE 0 END) AS unknown_count')
            ->selectRaw('COALESCE(SUM(CASE WHEN expected_packages IS NOT NULL THEN completed_packages ELSE 0 END), 0) AS completed_count')
            ->first();
        $todayStart = BusinessTime::local()->startOfDay()->utc();
        $packedToday = DB::table('fulfillment_run_packages')->where('status', 'packed')->where('packed_at', '>=', $todayStart)->count();
        $buyersToday = DB::table('fulfillment_run_buyers')->where('status', 'packed')->where('packed_at', '>=', $todayStart)->count();
        $team = User::query()->whereHas('roles', fn ($q) => $q->whereIn('name', ['fulfillment', 'fulfillment_admin']))->pluck('id');

        return [
            'stations_active' => FulfillmentRun::query()->whereNotNull('station_occupied_id')->count(),
            'stations_total' => FulfillmentStation::query()->where('active', true)->count(),
            'ready' => (int) ($open->ready_count ?? 0),
            'expected' => (int) ($open->expected_count ?? 0),
            'expected_unknown' => (int) ($open->unknown_count ?? 0),
            'completed_today' => $packedToday,
            'buyers_today' => $buyersToday,
            'remaining' => max(0, (int) ($open->expected_count ?? 0) - (int) ($open->completed_count ?? 0)),
            'issues' => DB::table('fulfillment_flags')->where('status', 'open')->count(),
            'working' => TimeEntry::query()->whereIn('user_id', $team)->whereNull('clocked_out_at')->count(),
        ] + $this->shipping();
    }

    /**
     * In transit / delivered over the last 30 days, grouped the same way as
     * Shipment Tracking. Thirty days of shipments move slowly, so the figure
     * is cached for two minutes instead of recounted on every refresh.
     */
    private function shipping(): array
    {
        return \Illuminate\Support\Facades\Cache::remember('fulfillment:home-shipping30', 120, function () {
            $out = ['in_transit' => 0, 'delivered' => 0];
            DB::table('shipments')->join('shows', 'shows.id', '=', 'shipments.show_id')
                ->where('shows.show_date', '>=', now()->subDays(30)->toDateString())
                ->select('shipments.status', DB::raw('COUNT(*) as total'))->groupBy('shipments.status')->get()
                ->each(function ($row) use (&$out) {
                    $bucket = \App\Filament\Pages\ShipmentOverview::bucket($row->status);
                    if (in_array($bucket, ['in_transit', 'shipped'], true)) $out['in_transit'] += (int) $row->total;
                    if ($bucket === 'delivered') $out['delivered'] += (int) $row->total;
                });

            return $out;
        });
    }

    #[Computed]
    public function todayShifts(): \Illuminate\Support\Collection
    {
        $start = BusinessTime::local()->startOfDay();
        $end = $start->copy()->addDay();

        return FulfillmentShift::query()->with(['user:id,name', 'station:id,name', 'shows:id,title'])
            ->where('starts_at', '<', $end)->where('ends_at', '>', $start)
            ->when(! $this->isManager(), fn ($q) => $q->where('user_id', auth()->id()))
            ->orderBy('starts_at')->limit(3)->get();
    }

    #[Computed]
    public function nextShift(): ?FulfillmentShift
    {
        return FulfillmentShift::query()->with('station:id,name')->where('user_id', auth()->id())
            ->where('ends_at', '>', now())->orderBy('starts_at')->first();
    }

    #[Computed]
    public function openIssues(): \Illuminate\Support\Collection
    {
        return \App\Models\FulfillmentFlag::query()->with(['show:id,title', 'buyer:id,letter,buyer_username', 'reporter:id,name'])
            ->where('status', 'open')
            ->when(! $this->isManager(), fn ($q) => $q->where('reported_by', auth()->id()))
            ->latest()->limit(3)->get();
    }

    /** The fulfillment team for the assign picker: id, name, initials. */
    #[Computed]
    public function team(): array
    {
        return User::query()->whereHas('roles', fn ($q) => $q->whereIn('name', ['fulfillment', 'fulfillment_admin']))
            ->orderBy('name')->get(['id', 'name'])
            ->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name,
                'initials' => collect(preg_split('/\s+/', trim($u->name)))->filter()->take(2)->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))->implode('')])
            ->all();
    }

    /** @deprecated use team() */
    #[Computed]
    public function teamOptions(): array
    {
        return collect($this->team)->pluck('name', 'id')->all();
    }

    /** Active stations for the assign picker, with what is on each now. */
    #[Computed]
    public function stationChoices(): array
    {
        $busy = FulfillmentRun::query()->whereNotNull('station_occupied_id')->with('show:id,title')->get(['id', 'show_id', 'station_occupied_id'])
            ->keyBy('station_occupied_id');

        return FulfillmentStation::query()->where('active', true)->orderBy('sort')->orderBy('id')->get(['id', 'name'])
            ->map(fn ($st) => ['id' => $st->id, 'name' => $st->name, 'busy' => $busy->get($st->id)?->show?->title])->all();
    }

    /**
     * Ready shows for the assign picker's search: no page re-render, just data.
     *
     * @return list<array{id:int,title:string,date:?string,users:list<int>,station:?int,packages:?int}>
     */
    #[\Livewire\Attributes\Renderless]
    public function assignShows(string $search = ''): array
    {
        abort_unless($this->isManager(), 403);
        $search = trim($search);

        return FulfillmentRun::query()->where('fulfillment_runs.status', FulfillmentRun::READY)
            ->join('shows', 'shows.id', '=', 'fulfillment_runs.show_id')
            ->when($search !== '', fn ($q) => $q->where('shows.title', 'like', '%' . addcslashes($search, '%_\\') . '%'))
            ->with('show.fulfillmentUsers:id')
            ->orderByDesc('shows.show_date')->orderByDesc('fulfillment_runs.id')
            ->limit(40)->get(['fulfillment_runs.id', 'fulfillment_runs.show_id', 'fulfillment_runs.station_id', 'fulfillment_runs.expected_packages', 'shows.title', 'shows.show_date'])
            ->map(fn ($r) => ['id' => $r->id, 'title' => $r->title, 'date' => $r->show_date ? \Illuminate\Support\Carbon::parse($r->show_date)->format('M j') : null,
                'users' => $r->show?->fulfillmentUsers->pluck('id')->map(fn ($id) => (int) $id)->values()->all() ?? [],
                'station' => $r->station_id ? (int) $r->station_id : null, 'packages' => $r->expected_packages])
            ->all();
    }

    /**
     * Save an assignment from the assign modal: who packs the show and,
     * optionally, the station. Re-renders only the parts it changes.
     *
     * @return array{ok:bool,message:string}
     */
    public function saveAssignment(int $runId, array $users = [], ?int $stationId = null): array
    {
        abort_unless($this->isManager(), 403);
        $run = FulfillmentRun::query()->with('show')->find($runId);
        if (! $run || ! $run->show) return ['ok' => false, 'message' => 'That show is no longer ready to pack.'];
        $users = array_values(array_unique(array_map('intval', array_filter($users, 'is_numeric'))));
        $stationId = $stationId ?: null;
        try {
            app(FulfillmentRunService::class)->assign($run->show, $users, auth()->user(), $stationId);
        } catch (ValidationException $e) {
            return ['ok' => false, 'message' => (string) collect($e->errors())->flatten()->first()];
        }
        unset($this->stations, $this->queue, $this->queueTotal, $this->stats, $this->stationChoices);
        foreach (['floor', 'queue', 'side'] as $island) $this->renderIsland($island);
        Notification::make()->title('Assignment saved')->success()->send();

        return ['ok' => true, 'message' => 'Assignment saved'];
    }

    /** Sign-off review: show it, and refresh what signing off changes. */
    protected function signoffOpened(): void
    {
        $this->renderIsland('signoff');
    }

    protected function signoffSaved(): void
    {
        unset($this->stats, $this->activity);
        foreach (['signoff', 'floor', 'side', 'feed'] as $island) $this->renderIsland($island);
    }

    public function statusPill(string $state): string
    {
        return match ($state) {
            'packing' => 'packing', 'ready' => 'ready', 'paused', 'hold' => 'paused',
            'completed' => 'completed', 'approved' => 'approved', default => '',
        };
    }

    public function stateLabel(string $state): string
    {
        return $state === 'hold' ? 'On hold' : FulfillmentRun::statusLabel($state);
    }
}
