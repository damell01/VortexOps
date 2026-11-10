<?php

namespace App\Filament\Pages;

use App\Models\FulfillmentEvent;
use App\Models\FulfillmentFlag;
use App\Models\FulfillmentRun;
use App\Models\FulfillmentRunBuyer;
use App\Models\FulfillmentShift;
use App\Models\FulfillmentStation;
use App\Models\Show;
use App\Models\User;
use App\Services\FulfillmentFlagService;
use App\Services\FulfillmentRunService;
use App\Services\FulfillmentScheduleService;
use App\Support\BusinessTime;
use App\Support\FulfillmentAccess;
use Carbon\CarbonImmutable;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\WithPagination;

/**
 * Schedule & Activity: the weekly schedule, every show's digital tracking
 * record (show log), issues, productivity and the camera footage lookup.
 */
class FulfillmentActivity extends Page
{
    use \App\Filament\Concerns\ReviewsFulfillmentSignoff;

    use WithPagination;

    /** The page draws its own header. */
    public function getHeading(): string { return ''; }

    protected static ?string $title = 'Schedule & Activity';
    protected static ?string $slug = 'fulfillment/schedule-activity';
    protected static bool $shouldRegisterNavigation = false;

    public const TABS = ['schedule' => 'Schedule', 'shows' => 'Show logs', 'issues' => 'Issues', 'productivity' => 'Productivity', 'lookup' => 'Footage lookup'];

    #[Url(as: 'tab')]
    public string $tab = 'schedule';

    #[Url(as: 'run')]
    public ?int $runId = null;

    #[Url(as: 'week')]
    public ?string $week = null;

    public string $logTab = 'overview';
    public string $scheduleView = 'week';
    public string $issueFilter = 'open';
    public string $showsFilter = 'all';
    public int $days = 7;
    public string $lookup = '';
    public array $resolution = [];


    /** Shift drawer. */
    public bool $shiftOpen = false;
    public ?int $shiftId = null;
    public array $shift = [];
    public bool $shiftSaving = false;
    public bool $repeatShift = false;
    public array $repeatDays = [];
    public ?string $repeatUntil = null;
    public string $showSearch = '';
    public bool $showPickerOpen = false;

    public function chooseShiftKind(string $kind): void
    {
        if (! in_array($kind, ['day', 'night', 'custom'], true)) return;
        $this->shift['kind'] = $kind === 'custom' ? '' : $kind;
        $this->shift['selection'] = $kind;
        if ($kind === 'day') { $this->shift['start'] = config('fulfillment.day_start', '08:00'); $this->shift['end'] = config('fulfillment.day_end', '16:00'); }
        if ($kind === 'night') { $this->shift['start'] = config('fulfillment.night_start', '19:00'); $this->shift['end'] = config('fulfillment.night_end', '23:00'); }
    }

    public function toggleShiftShow(int $id): void
    {
        abort_unless($this->isManager(), 403);
        if (! array_key_exists($id, $this->assignableShows) && ! in_array($id, array_map('intval', $this->shift['show_ids'] ?? []), true)) return;
        $ids = array_map('intval', $this->shift['show_ids'] ?? []);
        $this->shift['show_ids'] = array_values(array_map('strval', in_array($id, $ids, true) ? array_diff($ids, [$id]) : [...$ids, $id]));
    }

    public function shiftDuration(): string
    {
        try {
            $start = CarbonImmutable::parse('2000-01-01 ' . ($this->shift['start'] ?? '08:00'));
            $end = CarbonImmutable::parse('2000-01-01 ' . ($this->shift['end'] ?? '16:00'));
            if ($end->lessThanOrEqualTo($start)) $end = $end->addDay();
            $minutes = (int) $start->diffInMinutes($end);
            return sprintf('%dh %02dm%s', intdiv($minutes, 60), $minutes % 60, $end->toDateString() !== $start->toDateString() ? ' · overnight' : '');
        } catch (\Throwable) { return 'Choose valid times'; }
    }

    /** Station editor. */
    public string $newStation = '';

    public static function canAccess(): bool
    {
        return FulfillmentAccess::moduleEnabled() && FulfillmentAccess::isMember(auth()->user());
    }

    public function getView(): string
    {
        return 'filament.pages.fulfillment-activity';
    }

    public function getTitle(): string
    {
        return 'Schedule & Activity';
    }

    public function mount(): void
    {
        if (! array_key_exists($this->tab, self::TABS)) $this->tab = 'schedule';
        if ($this->runId) {
            $run = FulfillmentRun::query()->findOrFail($this->runId);
            $this->authorizeRun($run);
            if ($this->tab === 'schedule') $this->tab = 'shows';

        }
    }

    public function isManager(): bool
    {
        return FulfillmentAccess::isManager(auth()->user());
    }

    public function setTab(string $tab): void
    {
        if (array_key_exists($tab, self::TABS)) {
            $this->tab = $tab;
            $this->resetPage();
        }
    }

    private function authorizeRun(FulfillmentRun $run): void
    {
        abort_unless($this->isManager() || FulfillmentAccess::canWorkRun(auth()->user(), $run->id), 403);
    }

    // ── Schedule ───────────────────────────────────────────────────────────

    public function weekStart(): CarbonImmutable
    {
        $base = $this->week ? CarbonImmutable::parse($this->week, BusinessTime::zone()) : CarbonImmutable::now(BusinessTime::zone());

        return $base->startOfWeek()->startOfDay();
    }

    public function changeWeek(int $weeks): void
    {
        $this->week = $this->weekStart()->addWeeks($weeks)->toDateString();
    }

    #[Computed]
    public function weekShifts(): \Illuminate\Support\Collection
    {
        $start = $this->weekStart();

        return FulfillmentShift::query()->with(['user:id,name', 'station:id,name', 'shows:id,title,whatnot_channel_id', 'shows.channel:id,name', 'shows.streamers:id,name'])
            ->where('starts_at', '<', $start->addWeek()->utc())->where('ends_at', '>', $start->utc())
            ->when(! $this->isManager(), fn ($q) => $q->where('user_id', auth()->id()))
            ->orderBy('starts_at')->get();
    }

    #[Computed]
    public function team(): \Illuminate\Support\Collection
    {
        return User::query()->whereHas('roles', fn ($q) => $q->whereIn('name', ['fulfillment', 'fulfillment_admin']))
            ->when(! $this->isManager(), fn ($q) => $q->whereKey(auth()->id()))
            ->orderBy('name')->get(['id', 'name']);
    }

    #[Computed]
    public function stationList(): \Illuminate\Support\Collection
    {
        return FulfillmentStation::query()->orderBy('sort')->orderBy('id')->get();
    }

    #[Computed]
    public function assignableShows(): array
    {
        return Show::query()->whereDate('show_date', '>=', today()->subDays(14))->whereDate('show_date', '<=', today()->addDays(14))
            ->whereNotIn('status', ['cancelled'])->with('channel:id,name')->orderByDesc('show_date')->limit(150)->get(['id', 'title', 'show_date', 'whatnot_channel_id'])
            ->mapWithKeys(fn (Show $s) => [$s->id => $s->show_date?->format('M j') . ' · ' . ($s->channel?->name ? $s->channel->name . ' · ' : '') . \Illuminate\Support\Str::limit($s->title, 60)])->all();
    }

    public function newShift(?string $date = null, ?int $userId = null): void
    {
        abort_unless($this->isManager(), 403);
        $this->shiftId = null;
        $this->shift = ['user_id' => $userId ? (string) $userId : '', 'date' => $date ?? today()->toDateString(), 'start' => '08:00', 'end' => '16:00',
            'station_id' => '', 'kind' => 'day', 'selection' => 'day', 'notes' => '', 'show_ids' => []];
        $this->repeatShift = false; $this->repeatDays = []; $this->repeatUntil = null; $this->showSearch = ''; $this->showPickerOpen = false;
        $this->shiftOpen = true;
        $this->resetErrorBag();
    }

    public function editShift(int $id): void
    {
        abort_unless($this->isManager(), 403);
        $s = FulfillmentShift::query()->with('shows:id')->findOrFail($id);
        $tz = BusinessTime::zone();
        $this->shiftId = $s->id;
        $this->shift = ['user_id' => (string) $s->user_id, 'date' => $s->starts_at->timezone($tz)->toDateString(),
            'start' => $s->starts_at->timezone($tz)->format('H:i'), 'end' => $s->ends_at->timezone($tz)->format('H:i'),
            'station_id' => (string) ($s->station_id ?? ''), 'kind' => $s->kind ?? '', 'selection' => $s->kind ?: 'custom', 'notes' => $s->notes ?? '',
            'show_ids' => $s->shows->pluck('id')->map(fn ($v) => (string) $v)->all()];
        $this->repeatShift = false; $this->repeatDays = []; $this->repeatUntil = null; $this->showSearch = ''; $this->showPickerOpen = false;
        $this->shiftOpen = true;
        $this->resetErrorBag();
    }

    public function saveShift(): void
    {
        abort_unless($this->isManager(), 403);
        if ($this->shiftSaving) return;
        $this->shiftSaving = true;
        try {
        $this->validate([
            'shift.user_id' => ['required', 'integer'], 'shift.date' => ['required', 'date'],
            'shift.start' => ['required', 'date_format:H:i'], 'shift.end' => ['required', 'date_format:H:i'],
            'shift.station_id' => ['nullable', 'integer'], 'shift.kind' => ['nullable', 'in:day,night'],
            'shift.notes' => ['nullable', 'string', 'max:255'], 'shift.show_ids' => ['array'],
        ], [], ['shift.user_id' => 'employee']);
        $tz = BusinessTime::zone();
        $start = CarbonImmutable::parse($this->shift['date'] . ' ' . $this->shift['start'], $tz)->utc()->toDateTimeString();
        $end = CarbonImmutable::parse($this->shift['date'] . ' ' . $this->shift['end'], $tz)->utc()->toDateTimeString();
        // An end at or before the start is the next morning (overnight shift).
        if ($this->shift['end'] <= $this->shift['start']) {
            $end = CarbonImmutable::parse($this->shift['date'] . ' ' . $this->shift['end'], $tz)->addDay()->utc()->toDateTimeString();
        }
        if ($this->repeatShift && ! $this->shiftId) {
            $this->validate([
                'repeatDays' => ['required', 'array', 'min:1'],
                'repeatDays.*' => ['integer', 'between:1,7'],
                'repeatUntil' => ['required', 'date', 'after_or_equal:shift.date'],
            ]);
            if (CarbonImmutable::parse($this->repeatUntil)->greaterThan(CarbonImmutable::parse($this->shift['date'])->addMonths(6))) {
                $this->addError('repeatUntil', 'Repeat shifts can be scheduled up to six months ahead.');
                return;
            }
        }
        $service = app(FulfillmentScheduleService::class);
        $args = [(int) $this->shift['user_id'], $start, $end, $this->shift['station_id'] ? (int) $this->shift['station_id'] : null,
            $this->shift['notes'] ?: null, $this->shift['show_ids'] ?? [], $this->shift['kind'] ?: null];
        try {
            if ($this->repeatShift && ! $this->shiftId) {
                $created = 0;
                $day = CarbonImmutable::parse($this->shift['date'], $tz);
                $until = CarbonImmutable::parse($this->repeatUntil, $tz);
                while ($day->lessThanOrEqualTo($until)) {
                    if (in_array($day->dayOfWeekIso, array_map('intval', $this->repeatDays), true)) {
                        $a = $day->setTimeFromTimeString($this->shift['start'])->utc()->toDateTimeString();
                        $b = $day->setTimeFromTimeString($this->shift['end']);
                        if ($this->shift['end'] <= $this->shift['start']) $b = $b->addDay();
                        try {
                            $service->create(auth()->user(), (int) $this->shift['user_id'], $a, $b->utc()->toDateTimeString(), $args[3], $args[4], $args[5], $args[6]);
                            $created++;
                        } catch (ValidationException $e) {
                            if (! isset($e->errors()['starts_at'])) throw $e;
                        }
                    }
                    $day = $day->addDay();
                }
                if (! $created) throw ValidationException::withMessages(['starts_at' => 'No new shifts were created; all dates overlap existing shifts.']);
            } else {
                $this->shiftId ? $service->update(auth()->user(), $this->shiftId, ...$args) : $service->create(auth()->user(), ...$args);
            }
        } catch (ValidationException $e) {
            $this->addError('shift', collect($e->errors())->flatten()->first());
            return;
        }
        $this->shiftOpen = false;
        unset($this->weekShifts);
        Notification::make()->title('Shift saved')->success()->send();
        } finally { $this->shiftSaving = false; }
    }

    public function deleteShift(): void
    {
        abort_unless($this->isManager() && $this->shiftId, 403);
        app(FulfillmentScheduleService::class)->delete(auth()->user(), $this->shiftId);
        $this->shiftOpen = false;
        unset($this->weekShifts);
        Notification::make()->title('Shift removed')->success()->send();
    }

    public function copyPreviousWeek(): void
    {
        abort_unless($this->isManager(), 403);
        $count = app(FulfillmentScheduleService::class)->copyWeek(auth()->user(), $this->weekStart()->subWeek()->toDateString(), $this->weekStart()->toDateString());
        unset($this->weekShifts);
        Notification::make()->title($count ? "Copied {$count} shift(s) from last week" : 'Nothing new to copy')->success()->send();
    }

    public function addStation(): void
    {
        abort_unless($this->isManager(), 403);
        $this->validate(['newStation' => ['required', 'string', 'max:60', 'unique:fulfillment_stations,name']], [], ['newStation' => 'station name']);
        FulfillmentStation::query()->create(['name' => trim($this->newStation), 'active' => true, 'sort' => (int) FulfillmentStation::query()->max('sort') + 1]);
        $this->newStation = '';
        unset($this->stationList);
    }

    public function updateStation(int $id, string $field, string $value): void
    {
        abort_unless($this->isManager(), 403);
        $station = FulfillmentStation::query()->findOrFail($id);
        if ($field === 'camera_label') $station->camera_label = mb_substr(trim($value), 0, 64) ?: null;
        if ($field === 'name' && trim($value) !== '' && ! FulfillmentStation::query()->where('name', trim($value))->whereKeyNot($id)->exists()) $station->name = mb_substr(trim($value), 0, 60);
        if ($field === 'active') {
            if ($value === '0' && FulfillmentRun::query()->where('station_occupied_id', $id)->exists()) {
                Notification::make()->title('That station is packing a show right now')->warning()->send();
                return;
            }
            $station->active = $value === '1';
        }
        $station->save();
        unset($this->stationList);
    }

    // ── Show logs ──────────────────────────────────────────────────────────

    #[Computed]
    public function runs()
    {
        return FulfillmentRun::query()
            ->with(['show:id,title,show_date,whatnot_channel_id', 'show.channel:id,name', 'station:id,name', 'packers:id,name'])
            ->withCount(['flags as open_flags_count' => fn ($q) => $q->where('status', 'open')])
            ->when($this->showsFilter !== 'all', fn ($q) => $q->where('status', $this->showsFilter))
            ->when(! $this->isManager(), fn ($q) => $q->whereHas('packers', fn ($p) => $p->where('users.id', auth()->id())))
            ->orderByDesc(DB::raw('COALESCE(started_at, ready_at, created_at)'))
            ->paginate(10, pageName: 'runsPage');
    }

    #[Computed]
    public function logRun(): ?FulfillmentRun
    {
        if (! $this->runId) return null;
        $run = FulfillmentRun::query()->with(['show:id,title,show_date,start_time,whatnot_channel_id,whatnot_show_id', 'show.channel:id,name', 'show.streamers:id,name',
            'station:id,name,camera_label', 'packers:id,name', 'acknowledger:id,name', 'approver:id,name'])->find($this->runId);
        if ($run) $this->authorizeRun($run);

        return $run;
    }

    #[Computed]
    public function logData(): array
    {
        return $this->logRun ? $this->recordData($this->logRun) : [];
    }

    /** Buyers, packages, events, issues and who packed what for one show. */
    /** Opening the full-screen review closes the log drawer under it. */
    protected function signoffOpened(): void
    {
        $this->runId = null;
        unset($this->logRun, $this->logData);
    }

    protected function signoffSaved(): void
    {
        unset($this->runs);
    }

    public function openLog(int $runId): void
    {
        $this->runId = $runId;
        $this->logTab = 'overview';
        unset($this->logRun, $this->logData);
    }

    public function closeLog(): void
    {
        $this->runId = null;
        unset($this->logRun, $this->logData);
    }

    public function acknowledge(): void
    {
        abort_unless($this->runId, 404);
        try {
            app(FulfillmentRunService::class)->acknowledge($this->runId, auth()->user());
            Notification::make()->title('Confirmed as done')->success()->send();
        } catch (ValidationException $e) {
            Notification::make()->title(collect($e->errors())->flatten()->first())->warning()->send();
        }
        unset($this->logRun);
    }

    /** CSV of the whole tracking record: one line per buyer/package. */
    public function exportRun(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $run = $this->logRun;
        abort_unless($run, 404);
        $data = $this->logData;
        $tz = BusinessTime::zone();
        $name = 'fulfillment-' . \Illuminate\Support\Str::slug($run->show?->title ?? 'show') . '-' . $run->id . '.csv';

        return response()->streamDownload(function () use ($run, $data, $tz) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Show', $run->show?->title, 'Stream date', $run->show?->show_date?->format('Y-m-d'), 'Station', $run->station?->name,
                'Started', $run->started_at?->timezone($tz)->format('Y-m-d H:i'), 'Finished', $run->finished_at?->timezone($tz)->format('Y-m-d H:i'), 'Status', $run->status]);
            fputcsv($out, ['Letter', 'Buyer', 'Orders', 'Items', 'Packages', 'Status', 'Packed by', 'Selected at', 'Packed at']);
            foreach ($data['buyers'] as $b) {
                fputcsv($out, [$b->letter, $b->buyer_username, implode(' ', (array) $b->order_ids), $b->item_count,
                    $b->packages_count ? $b->packed_packages_count . '/' . $b->packages_count : '', $b->status, $b->packer?->name,
                    $b->packing_started_at?->timezone($tz)->format('Y-m-d H:i:s'), $b->packed_at?->timezone($tz)->format('Y-m-d H:i:s')]);
            }
            fclose($out);
        }, $name, ['Content-Type' => 'text/csv']);
    }

    // ── Issues ─────────────────────────────────────────────────────────────

    #[Computed]
    public function issues()
    {
        return FulfillmentFlag::query()->with(['show:id,title', 'buyer:id,letter,buyer_username', 'reporter:id,name', 'resolver:id,name', 'station:id,name'])
            ->when($this->issueFilter !== 'all', fn ($q) => $q->where('status', $this->issueFilter))
            ->when(! $this->isManager(), fn ($q) => $q->where('reported_by', auth()->id()))
            ->latest()->paginate(10, pageName: 'issuesPage');
    }

    public function resolveIssue(int $flagId): void
    {
        abort_unless($this->isManager(), 403);
        try {
            app(FulfillmentFlagService::class)->resolve(auth()->user(), $flagId, (string) ($this->resolution[$flagId] ?? ''));
            unset($this->resolution[$flagId]);
            Notification::make()->title('Issue resolved')->success()->send();
        } catch (ValidationException $e) {
            Notification::make()->title(collect($e->errors())->flatten()->first())->warning()->send();
        }
        unset($this->issues, $this->logData);
    }

    // ── Productivity ───────────────────────────────────────────────────────

    /** Last few packed buyers, offered as one-tap examples in the footage lookup. */
    #[Computed]
    public function recentBuyers(): \Illuminate\Support\Collection
    {
        return FulfillmentEvent::query()->where('type', 'buyer_packed')->whereNotNull('buyer_username')
            ->latest('id')->limit(30)->get(['buyer_letter', 'buyer_username'])->unique('buyer_username')->take(6)->values();
    }

    #[Computed]
    public function productivity(): \Illuminate\Support\Collection
    {
        $from = now()->subDays(max(1, min(90, $this->days)));
        $packages = DB::table('fulfillment_run_packages')->where('status', 'packed')->where('packed_at', '>=', $from)
            ->select('packed_by', DB::raw('COUNT(*) as n'))->groupBy('packed_by')->pluck('n', 'packed_by');
        $buyers = DB::table('fulfillment_run_buyers')->where('status', 'packed')->where('packed_at', '>=', $from)
            ->select('packed_by', DB::raw('COUNT(*) as n'))->groupBy('packed_by')->pluck('n', 'packed_by');
        $verified = DB::table('fulfillment_verifications')->whereIn('result', ['matched'])->where('created_at', '>=', $from)
            ->select('verified_by', DB::raw('SUM(quantity) as n'))->groupBy('verified_by')->pluck('n', 'verified_by');
        $flags = DB::table('fulfillment_flags')->where('created_at', '>=', $from)
            ->select('reported_by', DB::raw('COUNT(*) as n'))->groupBy('reported_by')->pluck('n', 'reported_by');
        $runs = FulfillmentRun::query()->whereNotNull('started_at')->where('started_at', '>=', $from)->with('packers:id')->get();

        return $this->team->map(function (User $user) use ($packages, $buyers, $verified, $flags, $runs) {
            $mine = $runs->filter(fn ($r) => $r->packers->contains('id', $user->id));
            $seconds = $mine->sum(fn (FulfillmentRun $r) => $r->activeSeconds());

            return [
                'user' => $user, 'packages' => (int) ($packages[$user->id] ?? 0), 'buyers' => (int) ($buyers[$user->id] ?? 0),
                'shows' => $mine->count(), 'hours' => round($seconds / 3600, 1), 'verified' => (int) ($verified[$user->id] ?? 0),
                'flags' => (int) ($flags[$user->id] ?? 0), 'completed' => $mine->whereIn('status', ['completed', 'approved'])->count(),
            ];
        })->sortByDesc(fn ($r) => [$r['packages'], $r['buyers']])->values();
    }

    // ── Footage lookup ─────────────────────────────────────────────────────

    #[Computed]
    public function lookupResults(): \Illuminate\Support\Collection
    {
        $term = trim($this->lookup);
        if (mb_strlen($term) < 2) return collect();

        return FulfillmentRunBuyer::query()
            ->where(fn ($q) => $q->where('buyer_username', 'like', '%' . $term . '%')->orWhere('order_ids', 'like', '%' . $term . '%'))
            ->whereHas('run', fn ($r) => $r->whereNotNull('started_at'))
            ->when(! $this->isManager(), fn ($q) => $q->whereHas('run.packers', fn ($p) => $p->where('users.id', auth()->id())))
            ->with(['run:id,show_id,station_id,started_at', 'run.show:id,title,show_date', 'run.station:id,name,camera_label', 'packer:id,name'])
            ->latest('id')->limit(40)->get();
    }
}
