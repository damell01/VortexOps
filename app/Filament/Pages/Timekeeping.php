<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HasModuleAccess;
use App\Models\TimeEntry;
use App\Models\User;
use App\Support\AdminModules;
use App\Services\TimeTrackingService;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\On;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class Timekeeping extends Page
{
    use HasModuleAccess;

    protected static string $moduleSlug  = 'timekeeping';
    protected static ?string $title = 'Timekeeping';

    // Anyone who might need to clock in/out gets in — rows are already scoped
    // to "my own entries only" for everyone except admin/owner (see
    // canSeeTeamEntries() below), so this is safe to open beyond admin.
    protected static function passesModuleAccessCheck(): bool
    {
        $user = auth()->user();

        return ($user?->isAdmin()
            || $user?->isOwner()
            || $user?->isStreamer()
            || $user?->isFulfillment()
            || $user?->isFulfillmentAdmin()) ?? false;
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return AdminModules::navigationGroupFor('timekeeping');
    }

    public static function getNavigationSort(): ?int
    {
        return 5;
    }

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-clock';
    }

    public function getSubheading(): ?string
    {
        $user = auth()->user();

        return (($user?->isAdmin() || $user?->isOwner()) ?? false)
            ? 'Clock in/out, and see the whole team\'s hours below.'
            : 'Clock in/out here — you only ever see your own entries.';
    }

    /** The admin dashboard cards: what needs doing, who is on the clock, and this week's hours. */
    public function adminTiles(): array
    {
        $weekStart = \App\Support\BusinessTime::local()->startOfWeek()->setTimezone('UTC');
        $week = TimeEntry::query()->where('clocked_in_at', '>=', $weekStart)->where('status', '!=', 'rejected')->get(['id', 'user_id', 'clocked_in_at', 'clocked_out_at', 'status', 'approved_minutes']);
        $minutes = (int) $week->sum(fn (TimeEntry $e) => $e->status === 'approved' && $e->approved_minutes !== null ? (int) $e->approved_minutes : (int) ($e->duration_minutes ?? 0));
        $canTa = TimeAttendance::canAccess();
        $local = \App\Support\BusinessTime::local();
        $reportMinutes = (int) round((float) $this->reportHours($local->copy()->startOfWeek()->toDateString(), $local->toDateString())->sum('hours_streamed') * 60);

        return [
            'review' => ['count' => $canTa ? TimeAttendance::reviewCount() : 0, 'url' => $canTa ? TimeAttendance::getUrl(['tab' => 'review']) : null],
            'now' => ['count' => TimeEntry::query()->whereNull('clocked_out_at')->count(), 'url' => $canTa ? TimeAttendance::getUrl(['tab' => 'now']) : null],
            'hours' => ['minutes' => $minutes, 'report_minutes' => $reportMinutes, 'people' => $week->pluck('user_id')->unique()->count(), 'url' => $canTa ? TimeAttendance::getUrl(['tab' => 'people']) : null],
            'entries' => ['count' => $week->count(), 'url' => $canTa ? TimeAttendance::getUrl(['tab' => 'all']) : null],
        ];
    }

    public function getView(): string
    {
        return 'filament.pages.timekeeping';
    }

    // ── State ────────────────────────────────────────────────────────────────

    public string $note = '';

    /** 'history' | 'team' */
    public string $tab = 'history';

    public function setTab(string $tab): void
    {
        $this->tab = $tab === 'team' && $this->canSeeTeamEntries() ? 'team' : 'history';
    }
    public int    $page = 1;
    public int    $perPage = 15;

    /**
     * 'this_week' | 'last_week' | 'this_month' | 'custom' — for both the entries list and team hours.
     * No "current pay period": pay periods differ from person to person, so one
     * period for the whole team would be wrong for someone.
     */
    public string $periodMode = 'this_week';
    public string $periodFrom = '';
    public string $periodTo   = '';

    public function setPeriodMode(string $mode): void
    {
        $this->periodMode = in_array($mode, ['this_week', 'last_week', 'this_month', 'custom'], true)
            ? $mode
            : 'this_week';
        $this->page = 1;

        if ($mode === 'custom' && ! $this->periodFrom) {
            [$from, $to]      = $this->resolvedPeriod();
            $this->periodFrom = $from;
            $this->periodTo   = $to;
        }
    }

    // ── Computed ─────────────────────────────────────────────────────────────

    public function getOpenEntryProperty(): ?TimeEntry
    {
        return TimeEntry::where('user_id', auth()->id())
            ->whereNull('clocked_out_at')
            ->latest('clocked_in_at')
            ->first();
    }

    public function getIsClockedInProperty(): bool
    {
        return $this->openEntry !== null;
    }

    public function getStatsProperty(): array
    {
        $uid        = auth()->id();
        $inProgress = $this->openEntry
            ? (int) $this->openEntry->clocked_in_at->diffInMinutes(now())
            : 0;

        $minutesFrom = function (string $from) use ($uid): int {
            return TimeEntry::where('user_id', $uid)
                ->whereNotNull('clocked_out_at')
                ->whereDate('clocked_in_at', '>=', $from)
                ->get(['clocked_in_at', 'clocked_out_at'])
                ->sum(fn ($e) => (int) $e->clocked_in_at->diffInMinutes($e->clocked_out_at));
        };

        return [
            'today'       => $minutesFrom(now()->toDateString()) + ($this->isClockedIn ? $inProgress : 0),
            'week'        => $minutesFrom(now()->startOfWeek()->toDateString()) + ($this->isClockedIn ? $inProgress : 0),
            'month'       => $minutesFrom(now()->startOfMonth()->toDateString()) + ($this->isClockedIn ? $inProgress : 0),
            'in_progress' => $inProgress,
        ];
    }

    private function canSeeTeamEntries(): bool
    {
        $user = auth()->user();

        return ($user?->isOwner() || $user?->isAdmin()) ?? false;
    }

    /** @return array{0: string, 1: string} [from, to] as date strings. */
    private function resolvedPeriod(): array
    {
        return match ($this->periodMode) {
            'last_week'  => [now()->subWeek()->startOfWeek()->toDateString(), now()->subWeek()->endOfWeek()->toDateString()],
            'this_month' => [now()->startOfMonth()->toDateString(), now()->toDateString()],
            'custom'     => [
                $this->periodFrom ?: now()->startOfWeek()->toDateString(),
                $this->periodTo   ?: now()->toDateString(),
            ],
            default => [now()->startOfWeek()->toDateString(), now()->toDateString()], // this_week
        };
    }

    public function getPeriodLabelProperty(): string
    {
        [$from, $to] = $this->resolvedPeriod();

        return Carbon::parse($from)->format('M j') . ' – ' . Carbon::parse($to)->format('M j, Y');
    }

    /**
     * Everything worked in the period, newest first: clocked time and the
     * hours written on show reports, side by side. Report hours are read
     * here, not edited; they belong to the report.
     */
    public function getEntriesProperty(): \Illuminate\Pagination\LengthAwarePaginator
    {
        [$from, $to] = $this->resolvedPeriod();
        $clock = TimeEntry::with(['user', 'show:id,title'])
            ->whereBetween('clocked_in_at', [Carbon::parse($from)->startOfDay(), Carbon::parse($to)->endOfDay()])
            ->when(! $this->canSeeTeamEntries(), fn ($q) => $q->where('user_id', auth()->id()))
            ->get();

        $rows = $clock->map(fn (TimeEntry $e) => ['at' => $e->clocked_in_at, 'clock' => $e, 'report' => null])
            ->concat($this->reportHours($from, $to)->map(fn ($r) => ['at' => $r->vxWorkedAt, 'clock' => null, 'report' => $r]))
            ->sortByDesc(fn ($row) => $row['at']->getTimestamp())
            ->values();

        // One line per person, their clocked time and show reports nested under it.
        $people = $rows->groupBy(fn ($row) => $row['clock']
                ? 'u'.$row['clock']->user_id
                : ($row['report']->streamer?->user_id ? 'u'.$row['report']->streamer->user_id : 's'.$row['report']->streamer_id))
            ->map(function ($personRows, $key) {
                $first = $personRows->first();
                $clock = $personRows->whereNotNull('clock');
                $reports = $personRows->whereNotNull('report');

                return [
                    'key' => $key,
                    'name' => $clock->first()['clock']?->user?->name ?? $first['report']?->streamer?->name ?? $first['clock']?->user?->name ?? 'Unknown',
                    'rows' => $personRows->values(),
                    'clock_minutes' => (int) $clock->sum(fn ($row) => (int) ($row['clock']->duration_minutes ?? 0)),
                    'clock_count' => $clock->count(),
                    'report_minutes' => (int) $reports->sum(fn ($row) => (int) round((float) $row['report']->hours_streamed * 60)),
                    'report_count' => $reports->count(),
                    'shows' => $personRows->map(fn ($row) => $row['clock']?->show_id ?? $row['report']?->show_id)->filter()->unique()->count(),
                    'needs_review' => $clock->contains(fn ($row) => $row['clock']->status === 'flagged'),
                    'latest' => $first['at'],
                ];
            })
            ->sortByDesc(fn ($p) => $p['latest']->getTimestamp())
            ->values();
        $this->entryTotal = $rows->count();

        $page = max(1, min($this->page, (int) ceil(max(1, $people->count()) / $this->perPage)));

        return new \Illuminate\Pagination\LengthAwarePaginator($people->forPage($page, $this->perPage)->values(), $people->count(), $this->perPage, $page);
    }

    /** How many entries (clocked and from reports) the period holds, across everyone. */
    public int $entryTotal = 0;

    /**
     * Hours written on submitted show reports for shows in the period: the
     * person's own, or everyone's for admins. Each carries vxWorkedAt (the
     * show's start, or its day) for sorting alongside clocked time.
     *
     * @return \Illuminate\Support\Collection<int, \App\Models\StreamerLogEntry>
     */
    public function reportHours(string $from, string $to): \Illuminate\Support\Collection
    {
        $own = $this->canSeeTeamEntries() ? null : (auth()->user()?->streamer()->value('id') ?? 0);

        return \App\Models\StreamerLogEntry::query()
            ->whereNotNull('submitted_at')
            ->where('hours_streamed', '>', 0)
            ->whereHas('show', fn ($q) => $q->whereDate('show_date', '>=', $from)->whereDate('show_date', '<=', $to)->where('status', '!=', 'cancelled'))
            ->when($own !== null, fn ($q) => $q->where('streamer_id', $own))
            ->with(['show:id,title,show_date,start_time', 'streamer:id,name,user_id'])
            ->get(['id', 'show_id', 'streamer_id', 'status', 'hours_streamed', 'submitted_at'])
            ->each(function ($r) {
                $day = Carbon::parse($r->show->show_date->toDateString(), \App\Support\BusinessTime::zone());
                $r->vxWorkedAt = $r->show->start_time ? $day->setTimeFrom($r->show->start_time) : $day->setTime(12, 0);
            });
    }

    /**
     * Each person's hours for the selected period: clocked time (hourly
     * team; admins and the owner are left out) plus the hours on their show
     * reports, so nobody's time is hidden just because it wasn't clocked.
     *
     * @return array<int, array{name: string, minutes: int, entries: int, report_minutes: int, reports: int, total: int}>
     */
    public function getTeamHoursProperty(): array
    {
        if (! $this->canSeeTeamEntries()) {
            return [];
        }

        [$from, $to] = $this->resolvedPeriod();
        $people = [];
        $row = fn (string $name) => ['name' => $name, 'minutes' => 0, 'entries' => 0, 'report_minutes' => 0, 'reports' => 0, 'total' => 0];

        TimeEntry::with('user')
            ->whereNotNull('clocked_out_at')
            ->whereBetween('clocked_in_at', [Carbon::parse($from)->startOfDay(), Carbon::parse($to)->endOfDay()])
            ->get(['user_id', 'clocked_in_at', 'clocked_out_at'])
            ->filter(fn ($e) => $e->user && ! $e->user->isAdmin() && ! $e->user->isOwner())
            ->each(function ($e) use (&$people, $row) {
                $key = 'u'.$e->user_id;
                $people[$key] ??= $row($e->user->name ?? 'Unknown');
                $people[$key]['minutes'] += (int) $e->clocked_in_at->diffInMinutes($e->clocked_out_at);
                $people[$key]['entries']++;
            });

        $this->reportHours($from, $to)->each(function ($r) use (&$people, $row) {
            $key = $r->streamer?->user_id ? 'u'.$r->streamer->user_id : 's'.$r->streamer_id;
            $people[$key] ??= $row($r->streamer?->name ?? 'Unknown');
            $people[$key]['report_minutes'] += (int) round((float) $r->hours_streamed * 60);
            $people[$key]['reports']++;
        });

        return collect($people)
            ->map(fn ($p) => ['total' => $p['minutes'] + $p['report_minutes']] + $p)
            ->sortByDesc('total')
            ->values()
            ->toArray();
    }

    // ── Actions ──────────────────────────────────────────────────────────────

    public function clockIn(): void
    {
        try {
            $entry = app(TimeTrackingService::class)->clockIn(auth()->user(), null, null, $this->note);
        } catch (ValidationException) {
            Notification::make()->title('Already clocked in')->warning()->send();
            return;
        }

        $this->note = '';

        Notification::make()
            ->title('Clocked in at ' . $this->formatTimeInUserTz($entry->clocked_in_at))
            ->success()
            ->send();
    }

    public function clockOut(): void
    {
        try {
            $entry = app(TimeTrackingService::class)->clockOut(auth()->user());
        } catch (ValidationException) {
            Notification::make()->title('Not currently clocked in')->warning()->send();
            return;
        }

        Notification::make()
            ->title('Clocked out at ' . $this->formatTimeInUserTz($entry->clocked_out_at) . ' — ' . TimeEntry::formatMinutes((int) $entry->duration_minutes) . ' logged')
            ->success()
            ->send();
    }

    #[On('clock-changed')]
    public function clockChanged(): void
    {
        // Re-render: stats and history read fresh on every render.
    }

    /** "show:12" / "reason:cleanup" options for one person's entry. */
    private function purposeOptions(?User $user, ?int $currentShowId = null): array
    {
        $user ??= auth()->user();
        $time = app(TimeTrackingService::class);
        $label = fn ($show) => ($show->show_date?->format('D M j') ?? '') . ($show->start_time ? ' ' . $show->start_time->format('g:i A') : '') . ' · ' . ($show->title ?: 'Show #' . $show->id);
        $mine = $time->linkableShows($user, $currentShowId)->mapWithKeys(fn ($show) => ['show:' . $show->id => $label($show)])->all();
        $others = $time->isReviewer(auth()->user())
            ? $time->linkableShows(auth()->user(), $currentShowId, true)->reject(fn ($show) => isset($mine['show:' . $show->id]))->mapWithKeys(fn ($show) => ['show:' . $show->id => $label($show)])->all()
            : [];
        $reasons = collect(TimeEntry::REASONS)->mapWithKeys(fn ($l, $key) => ['reason:' . $key => $l])->all();

        return array_filter([
            'Reasons' => $reasons,
            ($user->id === auth()->id() ? 'Your shows' : $user->name . "'s shows") => $mine,
            'Other shows' => $others,
        ]);
    }

    /** @return array{0:?int,1:?string} */
    private function splitPurpose(?string $purpose): array
    {
        return match (true) {
            str_starts_with((string) $purpose, 'show:') => [(int) substr($purpose, 5), null],
            str_starts_with((string) $purpose, 'reason:') => [null, substr($purpose, 7)],
            default => [null, null],
        };
    }

    private function entryFields(?User $user, ?int $showId = null): array
    {
        $tz = $this->userTimezone();

        return [
            DateTimePicker::make('in')->label('Clocked in')->timezone($tz)->seconds(false)->required(),
            DateTimePicker::make('out')->label('Clocked out')->timezone($tz)->seconds(false)->after('in'),
            Select::make('purpose')->label('Show or reason')->options($this->purposeOptions($user, $showId))->searchable()->placeholder('Choose later'),
            TextInput::make('note')->label('Note')->maxLength(255),
            Textarea::make('why')->label('Why? (optional)')->rows(2)
                ->helperText(fn () => app(TimeTrackingService::class)->isReviewer(auth()->user()) && $user?->id !== auth()->id()
                    ? 'Logged with the entry. An admin change counts as reviewed.'
                    : 'Logged with the entry. Changed times are flagged for an admin to review before they are paid.'),
        ];
    }

    public function editEntryAction(): Action
    {
        return Action::make('editEntry')
            ->label('Edit')->link()->size('sm')->icon('heroicon-m-pencil-square')
            ->modalHeading('Correct this time')->modalWidth('lg')
            ->modalSubmitActionLabel('Save change')
            ->schema(function (array $arguments) {
                $entry = TimeEntry::with('user')->find($arguments['entry'] ?? 0);

                return $this->entryFields($entry?->user, $entry?->show_id);
            })
            ->fillForm(function (array $arguments) {
                $entry = TimeEntry::find($arguments['entry'] ?? 0);

                return $entry ? [
                    'in' => $entry->clocked_in_at,
                    'out' => $entry->clocked_out_at,
                    'purpose' => $entry->show_id ? 'show:' . $entry->show_id : ($entry->reason ? 'reason:' . $entry->reason : null),
                    'note' => $entry->notes,
                ] : [];
            })
            ->action(function (array $data, array $arguments, Action $action) {
                $entry = TimeEntry::with('user')->findOrFail($arguments['entry'] ?? 0);
                [$showId, $reason] = $this->splitPurpose($data['purpose'] ?? null);
                try {
                    $entry = app(TimeTrackingService::class)->edit($entry, auth()->user(),
                        Carbon::parse($data['in'], config('app.timezone')),
                        filled($data['out'] ?? null) ? Carbon::parse($data['out'], config('app.timezone')) : null,
                        (string) ($data['why'] ?? ''), $showId, $reason, $data['note'] ?? null);
                } catch (ValidationException $e) {
                    Notification::make()->title(collect($e->errors())->flatten()->first())->danger()->send();
                    $action->halt();
                }
                Notification::make()->title('Time updated')
                    ->body($entry->status === 'flagged' ? 'Flagged for an admin to review.' : null)->success()->send();
            });
    }

    public function deleteEntryAction(): Action
    {
        return Action::make('deleteEntry')
            ->label('Delete')->link()->size('sm')->color('danger')->icon('heroicon-m-trash')
            ->visible(fn () => app(TimeTrackingService::class)->isReviewer(auth()->user()))
            ->requiresConfirmation()
            ->modalHeading('Delete this time entry?')
            ->modalDescription('It is removed from timekeeping and payroll. The deletion is recorded with your name.')
            ->modalSubmitActionLabel('Delete')
            ->action(function (array $arguments) {
                $entry = TimeEntry::find($arguments['entry'] ?? 0);
                if ($entry) {
                    app(TimeTrackingService::class)->delete($entry, auth()->user());
                    Notification::make()->title('Time entry deleted')->success()->send();
                }
            });
    }

    public function addTimeAction(): Action
    {
        return Action::make('addTime')
            ->label('Add missed time')->icon('heroicon-m-plus')->color('gray')->size('sm')
            ->modalHeading('Add time you forgot to clock')->modalWidth('lg')
            ->modalDescription('Added time is flagged for an admin to review before it is paid.')
            ->modalSubmitActionLabel('Add time')
            ->schema(fn () => [
                ...($this->canSeeTeamEntries() ? [Select::make('user_id')->label('Person')->options(User::orderBy('name')->pluck('name', 'id'))->searchable()->default(auth()->id())->required()] : []),
                ...$this->entryFields(auth()->user()),
            ])
            ->action(function (array $data, Action $action) {
                $user = $this->canSeeTeamEntries() && filled($data['user_id'] ?? null) ? User::findOrFail($data['user_id']) : auth()->user();
                [$showId, $reason] = $this->splitPurpose($data['purpose'] ?? null);
                if (blank($data['out'] ?? null)) {
                    Notification::make()->title('Enter when the time ended.')->danger()->send();
                    $action->halt();
                }
                try {
                    app(TimeTrackingService::class)->addManual($user, auth()->user(),
                        Carbon::parse($data['in'], config('app.timezone')), Carbon::parse($data['out'], config('app.timezone')),
                        (string) ($data['why'] ?? ''), $showId, $reason, $data['note'] ?? null);
                } catch (ValidationException $e) {
                    Notification::make()->title(collect($e->errors())->flatten()->first())->danger()->send();
                    $action->halt();
                }
                Notification::make()->title('Time added')->success()->send();
            });
    }

    public function canEdit(TimeEntry $entry): bool
    {
        $user = auth()->user();
        if (app(TimeTrackingService::class)->isReviewer($user)) {
            return true;
        }

        return $entry->user_id === $user?->id && $entry->status !== 'approved';
    }

    public function nextPage(): void
    {
        $this->page++;
    }

    public function prevPage(): void
    {
        if ($this->page > 1) {
            $this->page--;
        }
    }

    public function exportTeamHoursCsv(): StreamedResponse
    {
        $rows       = $this->teamHours;
        [$from, $to] = $this->resolvedPeriod();

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Name', 'Clocked hours', 'Clock entries', 'Show report hours', 'Show reports', 'Total hours']);
            foreach ($rows as $r) {
                fputcsv($out, [$r['name'], round($r['minutes'] / 60, 2), $r['entries'], round($r['report_minutes'] / 60, 2), $r['reports'], round($r['total'] / 60, 2)]);
            }
            fclose($out);
        }, "team-hours-{$from}-to-{$to}.csv", ['Content-Type' => 'text/csv']);
    }

    public function exportCsv(): StreamedResponse
    {
        [$from, $to] = $this->resolvedPeriod();
        $query = TimeEntry::with('user')
            ->whereNotNull('clocked_out_at')
            ->whereBetween('clocked_in_at', [Carbon::parse($from)->startOfDay(), Carbon::parse($to)->endOfDay()])
            ->orderByDesc('clocked_in_at');

        if (! $this->canSeeTeamEntries()) {
            $query->where('user_id', auth()->id());
        }

        $entries = $query->get();
        $reports = $this->reportHours($from, $to);

        $userTz = $this->userTimezone();
        return response()->streamDownload(function () use ($entries, $reports, $userTz) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['User', 'Date', 'Clocked In', 'Clocked Out', 'Duration (minutes)', 'Hours', 'Notes']);
            foreach ($reports as $r) {
                $minutes = (int) round((float) $r->hours_streamed * 60);
                fputcsv($out, [$r->streamer?->name ?? 'Unknown', $r->vxWorkedAt->format('Y-m-d'), '', '', $minutes, round($minutes / 60, 2), 'Show report: '.($r->show?->title ?? '')]);
            }
            foreach ($entries as $entry) {
                $minutes = (int) $entry->clocked_in_at->diffInMinutes($entry->clocked_out_at);
                $inTz = $entry->clocked_in_at->setTimezone($userTz);
                $outTz = $entry->clocked_out_at->setTimezone($userTz);
                fputcsv($out, [
                    $entry->user?->name ?? 'Unknown',
                    $inTz->format('Y-m-d'),
                    $inTz->format('Y-m-d H:i:s'),
                    $outTz->format('Y-m-d H:i:s'),
                    $minutes,
                    round($minutes / 60, 2),
                    $entry->notes ?? '',
                ]);
            }
            fclose($out);
        }, "time-entries-{$from}-to-{$to}.csv", ['Content-Type' => 'text/csv']);
    }

    public function userTimezone(): string
    {
        return auth()->user()?->timezone ?: \App\Support\BusinessTime::zone();
    }

    public function formatTimeInUserTz(Carbon $time): string
    {
        return $time->setTimezone($this->userTimezone())->format('g:i A');
    }

    public function saveDetectedTimezone(string $timezone): void
    {
        auth()->user()->update(['timezone' => $timezone]);

        Notification::make()
            ->title('Timezone updated to ' . $timezone)
            ->success()
            ->send();
    }
}
