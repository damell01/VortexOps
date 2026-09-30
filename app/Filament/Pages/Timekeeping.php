<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HasModuleAccess;
use App\Models\TimeEntry;
use App\Models\User;
use App\Models\WeeklyPayoutBatch;
use App\Support\AdminModules;
use Filament\Notifications\Notification;
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

    public function getView(): string
    {
        return 'filament.pages.timekeeping';
    }

    // ── State ────────────────────────────────────────────────────────────────

    public string $note = '';
    public int    $page = 1;
    public int    $perPage = 15;

    /** 'this_week' | 'last_week' | 'pay_period' | 'this_month' | 'custom' — admin team-hours view only. */
    public string $periodMode = 'this_week';
    public string $periodFrom = '';
    public string $periodTo   = '';

    public function setPeriodMode(string $mode): void
    {
        $this->periodMode = in_array($mode, ['this_week', 'last_week', 'pay_period', 'this_month', 'custom'], true)
            ? $mode
            : 'this_week';

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
            'pay_period' => $this->currentPayPeriod(),
            'custom'     => [
                $this->periodFrom ?: now()->startOfWeek()->toDateString(),
                $this->periodTo   ?: now()->toDateString(),
            ],
            default => [now()->startOfWeek()->toDateString(), now()->toDateString()], // this_week
        };
    }

    /** The pay run week covering today, if one exists yet — else the current calendar week. */
    private function currentPayPeriod(): array
    {
        $today = now()->toDateString();

        $batch = WeeklyPayoutBatch::where('week_start', '<=', $today)
            ->where('week_end', '>=', $today)
            ->first();

        return $batch
            ? [$batch->week_start->toDateString(), $batch->week_end->toDateString()]
            : [now()->startOfWeek()->toDateString(), now()->endOfWeek()->toDateString()];
    }

    public function getPeriodLabelProperty(): string
    {
        [$from, $to] = $this->resolvedPeriod();

        return Carbon::parse($from)->format('M j') . ' – ' . Carbon::parse($to)->format('M j, Y');
    }

    public function getEntriesProperty()
    {
        $query = TimeEntry::with('user')
            ->orderByDesc('clocked_in_at');

        if (! $this->canSeeTeamEntries()) {
            $query->where('user_id', auth()->id());
        }

        return $query->paginate($this->perPage, ['*'], 'page', $this->page);
    }

    /**
     * Hourly team's hours for the selected period, grouped by person — the
     * "who worked how much this pay period" view admins actually need.
     * Admins/owner are excluded here; they're not the hourly workers this
     * summary is for (their own entries still show in the History table).
     *
     * @return array<int, array{name: string, minutes: int, entries: int}>
     */
    public function getTeamHoursProperty(): array
    {
        if (! $this->canSeeTeamEntries()) {
            return [];
        }

        [$from, $to] = $this->resolvedPeriod();
        $toDateTime  = Carbon::parse($to)->endOfDay();

        return TimeEntry::with('user')
            ->whereNotNull('clocked_out_at')
            ->whereBetween('clocked_in_at', [$from, $toDateTime])
            ->get(['user_id', 'clocked_in_at', 'clocked_out_at'])
            ->filter(fn ($e) => $e->user && ! $e->user->isAdmin() && ! $e->user->isOwner())
            ->groupBy('user_id')
            ->map(fn ($entries) => [
                'name'    => $entries->first()->user->name ?? 'Unknown',
                'minutes' => $entries->sum(fn ($e) => (int) $e->clocked_in_at->diffInMinutes($e->clocked_out_at)),
                'entries' => $entries->count(),
            ])
            ->sortByDesc('minutes')
            ->values()
            ->toArray();
    }

    // ── Actions ──────────────────────────────────────────────────────────────

    public function clockIn(): void
    {
        if ($this->isClockedIn) {
            Notification::make()->title('Already clocked in')->warning()->send();
            return;
        }

        $now = now();
        TimeEntry::create([
            'user_id'       => auth()->id(),
            'clocked_in_at' => $now,
            'notes'         => trim($this->note) ?: null,
        ]);

        $this->note = '';

        Notification::make()
            ->title('Clocked in at ' . $this->formatTimeInUserTz($now))
            ->success()
            ->send();
    }

    public function clockOut(): void
    {
        $entry = $this->openEntry;

        if (! $entry) {
            Notification::make()->title('Not currently clocked in')->warning()->send();
            return;
        }

        $now = now();
        $entry->update(['clocked_out_at' => $now]);
        $minutes = $entry->clocked_in_at->diffInMinutes($now);

        Notification::make()
            ->title('Clocked out at ' . $this->formatTimeInUserTz($now) . ' — ' . TimeEntry::formatMinutes($minutes) . ' logged')
            ->success()
            ->send();
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
            fputcsv($out, ['Name', 'Hours', 'Minutes', 'Entries']);
            foreach ($rows as $r) {
                fputcsv($out, [$r['name'], round($r['minutes'] / 60, 2), $r['minutes'], $r['entries']]);
            }
            fclose($out);
        }, "team-hours-{$from}-to-{$to}.csv", ['Content-Type' => 'text/csv']);
    }

    public function exportCsv(): StreamedResponse
    {
        $query = TimeEntry::with('user')
            ->whereNotNull('clocked_out_at')
            ->orderByDesc('clocked_in_at');

        if (! $this->canSeeTeamEntries()) {
            $query->where('user_id', auth()->id());
        }

        $entries = $query->get();

        $userTz = $this->userTimezone();
        return response()->streamDownload(function () use ($entries, $userTz) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['User', 'Date', 'Clocked In', 'Clocked Out', 'Duration (minutes)', 'Hours', 'Notes']);
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
        }, 'time-entries-' . now()->format('Y-m-d') . '.csv', ['Content-Type' => 'text/csv']);
    }

    public function userTimezone(): string
    {
        return auth()->user()?->timezone ?? 'UTC';
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
