<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HasModuleAccess;
use App\Models\StreamerLogEntry;
use App\Models\TimeEntry;
use App\Models\TimeEntryChange;
use App\Models\User;
use App\Services\TimeTrackingService;
use App\Support\AdminModules;
use App\Support\BusinessTime;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Url;

/**
 * Time & Attendance — what admins settle before payroll pays Hours Worked:
 * time people edited, added by hand, left open or overlapped, and shows whose
 * report hours are missing or disagree with the clock.
 */
class TimeAttendance extends Page
{
    use HasModuleAccess;

    protected static string $moduleSlug = 'timekeeping';
    protected static ?string $title = 'Time & Attendance';
    protected static ?string $slug = 'time-attendance';

    #[Url]
    public string $tab = 'review';

    #[Url]
    public string $week = '';

    /** 'entry:{id}' or 'report:{id}' — the item open in the side panel. */
    public ?string $reviewing = null;

    public string $decision = '';
    public string $correctedHours = '';
    public string $correctedMinutes = '';
    public string $reviewNote = '';

    public string $person = '';
    public string $status = '';
    public int $page = 1;

    protected static function passesModuleAccessCheck(): bool
    {
        return app(TimeTrackingService::class)->isReviewer(auth()->user());
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return AdminModules::navigationGroupFor('timekeeping');
    }

    public static function getNavigationSort(): ?int { return 6; }
    public static function getNavigationIcon(): string|\BackedEnum|null { return 'heroicon-o-clipboard-document-check'; }
    public function getView(): string { return 'filament.pages.time-attendance'; }

    public static function getNavigationBadge(): ?string
    {
        $count = static::reviewCount();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string { return 'warning'; }

    public static function reviewCount(): int
    {
        return TimeEntry::needsReview()->count() + app(TimeTrackingService::class)->showHoursIssues()->count();
    }

    public function getSubheading(): ?string
    {
        return 'Payroll pays approved Hours Worked only. Settle what is flagged here before the pay run is finalized.';
    }

    public function mount(): void
    {
        if (! in_array($this->tab, ['review', 'people', 'now', 'all'], true)) {
            $this->tab = 'review';
        }
    }

    public function setTab(string $tab): void
    {
        $this->tab = in_array($tab, ['review', 'people', 'now', 'all'], true) ? $tab : 'review';
        $this->closePanel();
        $this->page = 1;
    }

    public function tz(): string
    {
        return auth()->user()?->timezone ?: BusinessTime::zone();
    }

    private function service(): TimeTrackingService
    {
        return app(TimeTrackingService::class);
    }

    // ── Needs review ────────────────────────────────────────────────────────

    /** @return Collection<int, array<string, mixed>> */
    public function reviewItems(): Collection
    {
        $entries = TimeEntry::needsReview()->with(['user:id,name', 'show:id,title'])->orderBy('clocked_in_at')->get()
            ->map(fn (TimeEntry $e) => [
                'key' => 'entry:'.$e->id,
                'person' => $e->user?->name ?? 'Unknown',
                'when' => $e->clocked_in_at,
                'what' => $e->purposeLabel(),
                'issue' => implode(' · ', $e->flagLabels()),
                'clock' => $e->duration_minutes,
                'report' => null,
                'tone' => $e->hasFlag('missed_clock_out') || $e->hasFlag('overlap') ? 'bad' : 'warn',
            ]);

        $reports = $this->service()->showHoursIssues()->map(fn (array $i) => [
            'key' => 'report:'.$i['entry']->id,
            'person' => $i['entry']->streamer?->name ?? 'Unknown',
            'when' => $i['entry']->show?->show_date,
            'what' => $i['entry']->show?->title ?? 'Show #'.$i['entry']->show_id,
            'issue' => TimeTrackingService::issueLabel($i['kind']),
            'clock' => $i['clock_minutes'],
            'report' => $i['report_minutes'],
            'tone' => $i['kind'] === 'missing' ? 'bad' : 'warn',
        ]);

        return $entries->concat($reports)->sortBy(fn ($i) => $i['when']?->timestamp ?? 0)->values();
    }

    public function openPanel(string $key): void
    {
        $this->reviewing = $key;
        $this->decision = '';
        $this->correctedHours = '';
        $this->correctedMinutes = '';
        $this->reviewNote = '';
        $this->resetErrorBag();
    }

    public function closePanel(): void
    {
        $this->reviewing = null;
    }

    /** @return array<string, mixed>|null */
    public function panel(): ?array
    {
        if (! $this->reviewing) {
            return null;
        }
        [$kind, $id] = explode(':', $this->reviewing) + [null, null];

        if ($kind === 'entry') {
            $entry = TimeEntry::with(['user:id,name,timezone', 'show:id,title,show_date,show_duration', 'changes.user:id,name'])->find((int) $id);

            return $entry ? ['kind' => 'entry', 'entry' => $entry, 'history' => $entry->changes] : null;
        }

        if ($kind === 'report') {
            $report = StreamerLogEntry::with(['show:id,title,show_date,show_duration', 'streamer:id,name,user_id'])->find((int) $id);
            if (! $report) {
                return null;
            }
            $clockEntries = TimeEntry::where('show_id', $report->show_id)->where('user_id', $report->streamer?->user_id ?? 0)->orderBy('clocked_in_at')->get();

            return [
                'kind' => 'report',
                'report' => $report,
                'clock_minutes' => $this->service()->clockMinutesForShow($report->show_id, $report->streamer?->user_id),
                'clock_entries' => $clockEntries,
                'history' => TimeEntryChange::with('user:id,name')->where('streamer_log_entry_id', $report->id)
                    ->orWhereIn('time_entry_id', $clockEntries->pluck('id'))->latest('id')->get(),
            ];
        }

        return null;
    }

    public function saveReview(): void
    {
        abort_unless(static::canAccess(), 403);
        $panel = $this->panel();
        if (! $panel) {
            return;
        }

        try {
            if ($panel['kind'] === 'entry') {
                $minutes = $this->decision === 'corrected'
                    ? ((int) $this->correctedHours) * 60 + (int) $this->correctedMinutes
                    : null;
                if ($this->decision === 'corrected' && $this->correctedHours === '' && $this->correctedMinutes === '') {
                    $minutes = null;
                }
                $this->service()->review($panel['entry'], auth()->user(), $this->decision, $minutes, $this->reviewNote);
            } else {
                $hours = $this->decision === 'corrected' && ($this->correctedHours !== '' || $this->correctedMinutes !== '')
                    ? ((int) $this->correctedHours) + ((int) $this->correctedMinutes) / 60
                    : null;
                $this->service()->resolveShowHours($panel['report'], auth()->user(), $this->decision, $hours, $this->reviewNote);
            }
        } catch (ValidationException $e) {
            $this->addError('decision', collect($e->errors())->flatten()->first());

            return;
        }

        Notification::make()->title('Saved')->body('Recorded with your name and the time. Pay runs pick this up on their next sync.')->success()->send();

        // Straight on to the next item, the way a queue should work.
        $next = $this->reviewItems()->first();
        $next ? $this->openPanel($next['key']) : $this->closePanel();
    }

    // ── By person ───────────────────────────────────────────────────────────

    /** @return array{0:Carbon,1:Carbon} */
    public function weekRange(): array
    {
        $tz = $this->tz();
        try {
            $start = $this->week !== '' ? Carbon::parse($this->week, $tz)->startOfWeek() : now($tz)->startOfWeek();
        } catch (\Throwable) {
            $start = now($tz)->startOfWeek();
        }

        return [$start, $start->copy()->endOfWeek()];
    }

    public function shiftWeek(int $by): void
    {
        [$start] = $this->weekRange();
        $this->week = $start->addWeeks(max(-52, min(52, $by)))->toDateString();
    }

    /** @return Collection<int, array{user:User,summary:array}> */
    public function people(): Collection
    {
        [$from, $to] = $this->weekRange();
        $userIds = TimeEntry::whereBetween('clocked_in_at', [$from->copy()->utc(), $to->copy()->utc()])->distinct()->pluck('user_id')
            ->merge(StreamerLogEntry::whereHas('show', fn ($q) => $q->whereBetween('show_date', [$from->toDateString(), $to->toDateString().' 23:59:59']))
                ->with('streamer:id,user_id')->get()->pluck('streamer.user_id'))
            ->filter()->unique();

        return User::whereIn('id', $userIds)->orderBy('name')->get()
            ->map(fn (User $u) => ['user' => $u, 'summary' => $this->service()->personSummary($u, $from, $to)]);
    }

    // ── Clocked in now / all entries ────────────────────────────────────────

    public function openEntries(): Collection
    {
        return TimeEntry::whereNull('clocked_out_at')->with(['user:id,name', 'show:id,title'])->orderBy('clocked_in_at')->get();
    }

    public function allEntries()
    {
        return TimeEntry::with(['user:id,name', 'show:id,title'])
            ->when($this->person !== '', fn ($q) => $q->where('user_id', (int) $this->person))
            ->when($this->status !== '', fn ($q) => $q->where('status', $this->status))
            ->orderByDesc('clocked_in_at')
            ->paginate(25, ['*'], 'page', $this->page);
    }

    public function updatedPerson(): void { $this->page = 1; }
    public function updatedStatus(): void { $this->page = 1; }
    public function gotoPage(int $page): void { $this->page = max(1, $page); }

    public function peopleOptions(): array
    {
        return User::whereIn('id', TimeEntry::distinct()->pluck('user_id'))->orderBy('name')->pluck('name', 'id')->all();
    }
}
