<?php

namespace App\Livewire;

use App\Models\TimeEntry;
use App\Services\TimeTrackingService;
use Filament\Notifications\Notification;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Clock in and out — on My Shows and on Timekeeping.
 *
 * Linking a show is optional; without one a reason (prep, cleanup...) says
 * what the time was for, and either can be set or changed after clocking in.
 */
class ClockWidget extends Component
{
    public string $showId = '';
    public string $reason = '';
    public string $note = '';
    public bool $changing = false;

    /** Timekeeping shows today / week / month / all-time totals inside the card. */
    public bool $withStats = false;

    /** On a dashboard the show/reason picker folds away until opened. */
    public bool $compact = false;

    /** Lightweight clock reminder used throughout the signed-in panel. */
    public bool $banner = false;

    private function service(): TimeTrackingService
    {
        return app(TimeTrackingService::class);
    }

    public function openEntry(): ?TimeEntry
    {
        $user = auth()->user();

        return $user ? TimeEntry::where('user_id', $user->id)->whereNull('clocked_out_at')->with('show:id,title')->latest('clocked_in_at')->first() : null;
    }

    /** Shows offered in the picker; see TimeTrackingService::pickerShows(). */
    public function pickerShows(): array
    {
        $user = auth()->user();

        return $user ? $this->service()->pickerShows($user, $this->openEntry()?->show_id ?: ($this->showId !== '' ? (int) $this->showId : null)) : [];
    }

    public function pickShow(int $id): void
    {
        $this->showId = (string) $id;
        $this->reason = '';
    }

    public function clearShow(): void
    {
        $this->showId = '';
    }

    public function pickReason(string $reason): void
    {
        if (! array_key_exists($reason, TimeEntry::REASONS)) {
            return;
        }
        $this->reason = $this->reason === $reason ? '' : $reason;
        $this->showId = '';
    }

    public function todayMinutes(): int
    {
        $user = auth()->user();
        $tz = $user?->timezone ?: \App\Support\BusinessTime::zone();
        $start = now($tz)->startOfDay()->utc();

        return (int) TimeEntry::where('user_id', $user?->id)->where('clocked_in_at', '>=', $start)->get()
            ->sum(fn (TimeEntry $e) => (int) $e->clocked_in_at->diffInMinutes($e->clocked_out_at ?? now()));
    }

    /** @return array{today:int,week:int,month:int,total:int} minutes, the open entry counted up to now */
    public function totals(): array
    {
        $user = auth()->user();
        $tz = $user?->timezone ?: \App\Support\BusinessTime::zone();
        $now = now($tz);
        $entries = TimeEntry::where('user_id', $user?->id)->where('status', '!=', 'rejected')->get(['clocked_in_at', 'clocked_out_at']);
        $sum = fn ($from) => (int) $entries->filter(fn ($e) => $from === null || $e->clocked_in_at->gte($from))
            ->sum(fn ($e) => (int) $e->clocked_in_at->diffInMinutes($e->clocked_out_at ?? now()));

        return [
            'today' => $sum($now->copy()->startOfDay()->utc()),
            'week' => $sum($now->copy()->startOfWeek()->utc()),
            'month' => $sum($now->copy()->startOfMonth()->utc()),
            'total' => $sum(null),
        ];
    }

    public function clockIn(): void
    {
        try {
            $entry = $this->service()->clockIn(auth()->user(), $this->showId !== '' ? (int) $this->showId : null, $this->reason ?: null, $this->note);
        } catch (ValidationException $e) {
            Notification::make()->title(collect($e->errors())->flatten()->first())->warning()->send();

            return;
        }
        $this->reset(['showId', 'reason', 'note', 'changing']);
        Notification::make()->title('Clocked in')->body($entry->show_id ? 'Linked to '.(\App\Models\Show::whereKey($entry->show_id)->value('title') ?? 'the show') : 'Link a show or give a reason any time before you clock out.')->success()->send();
        $this->dispatch('clock-changed');
    }

    public function clockOut(): void
    {
        try {
            $entry = $this->service()->clockOut(auth()->user());
        } catch (ValidationException $e) {
            Notification::make()->title(collect($e->errors())->flatten()->first())->warning()->send();

            return;
        }
        Notification::make()->title('Clocked out — '.TimeEntry::formatMinutes((int) $entry->duration_minutes))
            ->body($entry->status === 'flagged' ? 'This entry was flagged for an admin to review: '.implode(', ', $entry->flagLabels()).'.' : null)
            ->{$entry->status === 'flagged' ? 'warning' : 'success'}()->send();
        $this->dispatch('clock-changed');
    }

    public function startChange(): void
    {
        $entry = $this->openEntry();
        $this->showId = (string) ($entry?->show_id ?? '');
        $this->reason = (string) ($entry?->reason ?? '');
        $this->note = (string) ($entry?->notes ?? '');
        $this->changing = true;
    }

    public function saveLink(): void
    {
        $entry = $this->openEntry();
        if (! $entry) {
            return;
        }
        try {
            $this->service()->link($entry, auth()->user(), $this->showId !== '' ? (int) $this->showId : null, $this->reason ?: null, $this->note);
        } catch (ValidationException $e) {
            Notification::make()->title(collect($e->errors())->flatten()->first())->warning()->send();

            return;
        }
        $this->reset(['showId', 'reason', 'note', 'changing']);
        $this->dispatch('clock-changed');
    }

    #[On('clock-changed')]
    public function refresh(): void
    {
    }

    /** Pick the Pokémon that keeps you company on the clock. */
    public function chooseBuddy(string $buddy): void
    {
        $user = auth()->user();
        if (! $user || ! in_array($buddy, \App\Support\Buddy::CHOICES, true)) {
            return;
        }
        $user->forceFill(['buddy' => $buddy])->save();
    }

    public function render()
    {
        if ($this->banner) {
            return view('livewire.clock-banner', ['entry' => $this->openEntry()]);
        }

        $picker = $this->pickerShows();

        return view('livewire.clock-widget', [
            'entry' => $this->openEntry(),
            'picker' => $picker,
            'selected' => $this->showId !== '' ? collect($picker)->firstWhere('id', (int) $this->showId) : null,
            'isReviewer' => $this->service()->isReviewer(auth()->user()),
            'reasons' => TimeEntry::REASONS,
            'today' => $this->todayMinutes(),
            'totals' => $this->withStats ? $this->totals() : null,
            'buddy' => \App\Support\Buddy::for(auth()->user()),
        ]);
    }
}
