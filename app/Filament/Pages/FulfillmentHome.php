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
    /** The page draws its own header. */
    public function getHeading(): string { return ''; }

    protected static ?string $title = 'Fulfillment';
    protected static ?string $slug = 'fulfillment';
    protected static bool $shouldRegisterNavigation = false;

    /** Assign drawer state. */
    /** Ready queue: all shows, long jobs (slow-pack) or the quick ones. */
    #[\Livewire\Attributes\Url(as: 'length')]
    public string $length = 'all';

    public function updatedLength(): void
    {
        if (! in_array($this->length, ['all', 'slow', 'quick'], true)) $this->length = 'all';
        $this->queuePage = 1;
        unset($this->queue);
    }

    /** The ready queue shows a page at a time, never one long list. */
    public const QUEUE_PER_PAGE = 5;

    public int $queuePage = 1;

    public function queuePageCount(): int
    {
        return max(1, (int) ceil($this->queue->count() / self::QUEUE_PER_PAGE));
    }

    public function queueSlice(): \Illuminate\Support\Collection
    {
        $this->queuePage = max(1, min($this->queuePage, $this->queuePageCount()));

        return $this->queue->forPage($this->queuePage, self::QUEUE_PER_PAGE)->values();
    }

    public function setQueuePage(int $page): void
    {
        $this->queuePage = max(1, min($page, $this->queuePageCount()));
    }

    public ?int $assignRunId = null;
    public array $assignUsers = [];
    public ?int $assignStation = null;
    /** Opened from an idle station: the manager still has to pick the show. */
    public bool $assignPickShow = false;

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
        // Pick up shows that became ready since the scheduler last ran.
        app(FulfillmentQueue::class)->sync();
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
            ->with(['show:id,title,show_date,start_time,whatnot_channel_id', 'show.channel:id,name', 'show.streamers:id,name', 'packers:id,name', 'show.fulfillmentUsers:id,name'])
            ->withCount(['flags as open_flags_count' => fn ($q) => $q->where('status', 'open')])
            ->get();

        return $stations->map(function (FulfillmentStation $station) use ($runs) {
            $run = $runs->first(fn ($r) => (int) $r->station_occupied_id === $station->id)
                ?? $runs->first(fn ($r) => $r->status === FulfillmentRun::READY && (int) $r->station_id === $station->id);

            return ['station' => $station, 'run' => $run, 'state' => $this->stationState($run)];
        });
    }

    public function stationState(?FulfillmentRun $run): string
    {
        if (! $run) return 'idle';
        if ($run->isOnStation() && ($run->open_flags_count ?? 0) > 0) return 'hold';
        return $run->status;
    }

    #[Computed]
    public function queue(): \Illuminate\Support\Collection
    {
        return app(FulfillmentQueue::class)->queueRows(auth()->user(), 50, $this->length)
            ->loadCount(['flags as open_flags_count' => fn ($q) => $q->where('status', 'open')]);
    }

    #[Computed]
    public function stats(): array
    {
        $open = FulfillmentRun::query()->whereIn('status', FulfillmentRun::OPEN)->get(['id', 'status', 'expected_packages', 'completed_packages', 'buyer_count']);
        $known = $open->whereNotNull('expected_packages');
        $todayStart = BusinessTime::local()->startOfDay()->utc();
        $packedToday = DB::table('fulfillment_run_packages')->where('status', 'packed')->where('packed_at', '>=', $todayStart)->count();
        $buyersToday = DB::table('fulfillment_run_buyers')->where('status', 'packed')->where('packed_at', '>=', $todayStart)->count();
        $team = User::query()->whereHas('roles', fn ($q) => $q->whereIn('name', ['fulfillment', 'fulfillment_admin']))->pluck('id');

        return [
            'stations_active' => FulfillmentRun::query()->whereNotNull('station_occupied_id')->count(),
            'stations_total' => FulfillmentStation::query()->where('active', true)->count(),
            'ready' => $open->where('status', FulfillmentRun::READY)->count(),
            'expected' => $known->sum('expected_packages'),
            'expected_unknown' => $open->whereNull('expected_packages')->count(),
            'completed_today' => $packedToday,
            'buyers_today' => $buyersToday,
            'remaining' => max(0, $known->sum('expected_packages') - $known->sum('completed_packages')),
            'issues' => DB::table('fulfillment_flags')->where('status', 'open')->count(),
            'working' => TimeEntry::query()->whereIn('user_id', $team)->whereNull('clocked_out_at')->count(),
        ] + $this->shipping();
    }

    /** In transit / delivered over the last 30 days, grouped the same way as Shipment Tracking. */
    private function shipping(): array
    {
        $out = ['in_transit' => 0, 'delivered' => 0];
        \App\Models\Shipment::query()
            ->whereHas('show', fn ($q) => $q->where('show_date', '>=', now()->subDays(30)->toDateString()))
            ->select('status', DB::raw('COUNT(*) as total'))->groupBy('status')->get()
            ->each(function ($row) use (&$out) {
                $bucket = \App\Filament\Pages\ShipmentOverview::bucket($row->status);
                if (in_array($bucket, ['in_transit', 'shipped'], true)) $out['in_transit'] += (int) $row->total;
                if ($bucket === 'delivered') $out['delivered'] += (int) $row->total;
            });

        return $out;
    }

    #[Computed]
    public function todayShifts(): \Illuminate\Support\Collection
    {
        $start = BusinessTime::local()->startOfDay();
        $end = $start->copy()->addDay();

        return FulfillmentShift::query()->with(['user:id,name', 'station:id,name', 'shows:id,title'])
            ->where('starts_at', '<', $end)->where('ends_at', '>', $start)
            ->when(! $this->isManager(), fn ($q) => $q->where('user_id', auth()->id()))
            ->orderBy('starts_at')->limit(30)->get();
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
            ->latest()->limit(4)->get();
    }

    #[Computed]
    public function teamOptions(): array
    {
        return User::query()->whereHas('roles', fn ($q) => $q->whereIn('name', ['fulfillment', 'fulfillment_admin']))
            ->orderBy('name')->pluck('name', 'id')->all();
    }

    public function openAssign(int $runId): void
    {
        abort_unless($this->isManager(), 403);
        $run = FulfillmentRun::query()->with('show.fulfillmentUsers:id')->findOrFail($runId);
        $this->assignRunId = $run->id;
        $this->assignUsers = $run->show?->fulfillmentUsers->pluck('id')->map(fn ($id) => (string) $id)->all() ?? [];
        $this->assignStation = $run->station_id;
        $this->resetErrorBag();
    }

    /** "Assign show" on an idle station: choose a ready show for it. */
    public function openAssignToStation(int $stationId): void
    {
        abort_unless($this->isManager(), 403);
        abort_unless(FulfillmentStation::query()->whereKey($stationId)->where('active', true)->exists(), 404);
        $this->reset('assignRunId', 'assignUsers');
        $this->assignStation = $stationId;
        $this->assignPickShow = true;
        $this->resetErrorBag();
    }

    public function updatedAssignRunId(): void
    {
        $run = $this->assignRunId ? FulfillmentRun::query()->with('show.fulfillmentUsers:id')->find($this->assignRunId) : null;
        $this->assignUsers = $run?->show?->fulfillmentUsers->pluck('id')->map(fn ($id) => (string) $id)->all() ?? [];
    }

    /** Ready shows a manager can put on a station. */
    public function readyRunOptions(): array
    {
        return FulfillmentRun::query()->where('status', FulfillmentRun::READY)->with('show:id,title,show_date')
            ->get()->sortByDesc(fn ($r) => $r->show?->show_date)
            ->mapWithKeys(fn ($r) => [$r->id => ($r->show?->show_date?->format('M j') ?? '') . ' · ' . ($r->show?->title ?? 'Show')])->all();
    }

    public function closeAssign(): void
    {
        $this->reset('assignRunId', 'assignUsers', 'assignStation', 'assignPickShow');
    }

    public function saveAssign(): void
    {
        abort_unless($this->isManager(), 403);
        $this->validate([
            'assignRunId' => ['required', 'integer'],
        ], [
            'assignRunId.required' => 'Choose a show to assign.',
        ]);
        $this->validate([
            'assignUsers' => ['array'],
            'assignUsers.*' => ['integer'],
            'assignStation' => ['nullable', 'integer'],
        ]);
        $run = FulfillmentRun::query()->with('show')->findOrFail($this->assignRunId);
        try {
            app(FulfillmentRunService::class)->assign($run->show, $this->assignUsers, auth()->user(), $this->assignStation ?: null);
        } catch (ValidationException $e) {
            $this->addError('assignUsers', collect($e->errors())->flatten()->first());
            return;
        }
        $this->closeAssign();
        unset($this->stations, $this->queue);
        Notification::make()->title('Assignment saved')->success()->send();
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
