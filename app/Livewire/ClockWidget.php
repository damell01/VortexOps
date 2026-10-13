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

    private function service(): TimeTrackingService
    {
        return app(TimeTrackingService::class);
    }

    public function openEntry(): ?TimeEntry
    {
        $user = auth()->user();

        return $user ? TimeEntry::where('user_id', $user->id)->whereNull('clocked_out_at')->with('show:id,title')->latest('clocked_in_at')->first() : null;
    }

    public function shows(): array
    {
        $user = auth()->user();
        if (! $user) {
            return [];
        }
        return $this->service()->linkableShows($user, $this->openEntry()?->show_id)
            ->mapWithKeys(fn ($show) => [$show->id => trim(($show->show_date?->format('D M j') ?? '').($show->start_time ? ' '.$show->start_time->format('g:i A') : '').' · '.($show->title ?: 'Show #'.$show->id))])
            ->all();
    }

    public function todayMinutes(): int
    {
        $user = auth()->user();
        $tz = $user?->timezone ?: \App\Support\BusinessTime::zone();
        $start = now($tz)->startOfDay()->utc();

        return (int) TimeEntry::where('user_id', $user?->id)->where('clocked_in_at', '>=', $start)->get()
            ->sum(fn (TimeEntry $e) => (int) $e->clocked_in_at->diffInMinutes($e->clocked_out_at ?? now()));
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
        Notification::make()->title('Clocked in')->body($entry->show_id ? 'Linked to '.$this->shows()[$entry->show_id] : 'Link a show or give a reason any time before you clock out.')->success()->send();
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

    public function render()
    {
        return view('livewire.clock-widget', [
            'entry' => $this->openEntry(),
            'shows' => $this->shows(),
            'reasons' => TimeEntry::REASONS,
            'today' => $this->todayMinutes(),
        ]);
    }
}
