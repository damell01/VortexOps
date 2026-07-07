<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HasModuleAccess;
use App\Filament\Concerns\HasAdminNavVisibility;
use App\Models\TimeEntry;
use App\Models\User;
use App\Support\AdminModules;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class Timekeeping extends Page
{
    use HasModuleAccess, HasAdminNavVisibility;

    protected static string $moduleSlug  = 'timekeeping';
    protected static string $featureSlug = 'timekeeping_page';
    protected static ?string $title = 'Timekeeping';

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

    public function getView(): string
    {
        return 'filament.pages.timekeeping';
    }

    // ── State ────────────────────────────────────────────────────────────────

    public string $note = '';
    public int    $page = 1;
    public int    $perPage = 15;

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

    public function getEntriesProperty()
    {
        $query = TimeEntry::with('user')
            ->orderByDesc('clocked_in_at');

        if (! auth()->user()?->isOwner()) {
            $query->where('user_id', auth()->id());
        }

        return $query->paginate($this->perPage, ['*'], 'page', $this->page);
    }

    public function getTeamSummaryProperty(): array
    {
        if (! auth()->user()?->isOwner()) {
            return [];
        }

        $weekStart = now()->startOfWeek()->toDateString();

        return TimeEntry::with('user')
            ->whereNotNull('clocked_out_at')
            ->whereDate('clocked_in_at', '>=', $weekStart)
            ->get(['user_id', 'clocked_in_at', 'clocked_out_at'])
            ->groupBy('user_id')
            ->map(fn ($entries, $userId) => [
                'name'    => $entries->first()->user->name ?? 'Unknown',
                'minutes' => $entries->sum(
                    fn ($e) => (int) $e->clocked_in_at->diffInMinutes($e->clocked_out_at)
                ),
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

        TimeEntry::create([
            'user_id'       => auth()->id(),
            'clocked_in_at' => now(),
            'notes'         => trim($this->note) ?: null,
        ]);

        $this->note = '';

        Notification::make()
            ->title('Clocked in at ' . now()->format('g:i A'))
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

        $entry->update(['clocked_out_at' => now()]);
        $minutes = $entry->clocked_in_at->diffInMinutes(now());

        Notification::make()
            ->title('Clocked out — ' . TimeEntry::formatMinutes($minutes) . ' logged')
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

    public function exportCsv(): StreamedResponse
    {
        $query = TimeEntry::with('user')
            ->whereNotNull('clocked_out_at')
            ->orderByDesc('clocked_in_at');

        if (! auth()->user()?->isOwner()) {
            $query->where('user_id', auth()->id());
        }

        $entries = $query->get();

        return response()->streamDownload(function () use ($entries) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['User', 'Date', 'Clocked In', 'Clocked Out', 'Duration (minutes)', 'Hours', 'Notes']);
            foreach ($entries as $entry) {
                $minutes = (int) $entry->clocked_in_at->diffInMinutes($entry->clocked_out_at);
                fputcsv($out, [
                    $entry->user?->name ?? 'Unknown',
                    $entry->clocked_in_at->format('Y-m-d'),
                    $entry->clocked_in_at->format('Y-m-d H:i:s'),
                    $entry->clocked_out_at->format('Y-m-d H:i:s'),
                    $minutes,
                    round($minutes / 60, 2),
                    $entry->notes ?? '',
                ]);
            }
            fclose($out);
        }, 'time-entries-' . now()->format('Y-m-d') . '.csv', ['Content-Type' => 'text/csv']);
    }
}
