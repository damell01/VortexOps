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
    protected static ?string $title = 'Fulfillment';
    protected static ?string $slug = 'fulfillment';
    protected static bool $shouldRegisterNavigation = false;

    /** Assign drawer state. */
    public ?int $assignRunId = null;
    public array $assignUsers = [];
    public ?int $assignStation = null;

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
        return app(FulfillmentQueue::class)->queueRows(auth()->user())
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
        ];
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
            ->latest()->limit(8)->get();
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

    public function closeAssign(): void
    {
        $this->reset('assignRunId', 'assignUsers', 'assignStation');
    }

    public function saveAssign(): void
    {
        abort_unless($this->isManager(), 403);
        $this->validate([
            'assignRunId' => ['required', 'integer'],
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
