<?php

namespace App\Filament\Pages;

use App\Models\Show;
use App\Models\StreamerLogEntry;
use App\Models\Streamer;
use App\Support\AdminModules;
use Filament\Panel;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;

class Shows extends Page
{
    protected static string $moduleSlug = 'streams';
    protected static ?string $title = 'Shows';

    public string $filterStatus = 'all'; // all, active, completed, closed
    public string $filterStreamer = '';
    public string $searchQuery = '';
    public string $sortBy = 'date';

    public function getSubheading(): ?string
    {
        return 'Manage shows, track end-of-stream submissions, and view streamer reports.';
    }

    public function getView(): string
    {
        return 'filament.pages.shows';
    }

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-presentation-chart-line';
    }

    public static function getNavigationGroup(): ?string
    {
        return AdminModules::navigationGroupFor('streams');
    }

    public static function getNavigationSort(): ?int
    {
        return 20;
    }

    public static function getNavigationLabel(): string
    {
        return 'Shows';
    }

    public static function getSlug(?Panel $panel = null): string
    {
        return 'shows-overview';
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();
        return AdminModules::isEnabled('streams')
            && ($user?->isAdmin() || $user?->isStreamer());
    }


    #[Computed]
    public function streamers(): Collection
    {
        $user = auth()->user();

        if ($user?->isAdmin()) {
            return Streamer::orderBy('name')->get();
        }

        // Streamers only see their own
        return $user?->streamer ? collect([$user->streamer]) : collect();
    }

    #[Computed]
    public function shows(): Collection
    {
        $user = auth()->user();
        $query = Show::query();

        // Filter by status
        if ($this->filterStatus !== 'all') {
            $query->where('status', $this->filterStatus);
        }

        // Filter by streamer
        if ($this->filterStreamer && $user?->isAdmin()) {
            $query->whereHas('streamers', fn ($q) => $q->where('streamers.id', $this->filterStreamer));
        } elseif ($user?->isStreamer()) {
            $query->whereHas('streamers', fn ($q) => $q->where('streamers.id', $user->streamer->id));
        }

        // Search
        if ($this->searchQuery) {
            $query->where(function ($q) {
                $q->where('title', 'like', "%{$this->searchQuery}%")
                    ->orWhere('notes', 'like', "%{$this->searchQuery}%");
            });
        }

        // Sort
        if ($this->sortBy === 'date') {
            $query->orderBy('show_date', 'desc');
        } elseif ($this->sortBy === 'revenue') {
            $query->orderBy('gross_revenue', 'desc');
        }

        return $query
            ->with(['streamers', 'orders', 'streamerLogEntry'])
            ->limit(100)
            ->get();
    }

    public function clearFilters(): void
    {
        $this->filterStatus = 'all';
        $this->filterStreamer = '';
        $this->searchQuery = '';
        $this->sortBy = 'date';
    }

    public function requestFormSubmission($showId): void
    {
        $show = Show::findOrFail((int) $showId);

        // Send notifications to all streamers on this show
        foreach ($show->streamers as $streamer) {
            if ($streamer->user) {
                \Filament\Notifications\Notification::make()
                    ->title("📝 Form Submission Requested")
                    ->body("Admin is requesting you submit the end-of-stream form for \"{$show->title}\"")
                    ->info()
                    ->sendToDatabase($streamer->user);
            }
        }

        \Filament\Notifications\Notification::make()
            ->title("✓ Submission request sent")
            ->body("Notification sent to " . $show->streamers->count() . " streamer(s)")
            ->success()
            ->send();
    }

    public function requestFormResubmission($showId): void
    {
        $show = Show::findOrFail((int) $showId);
        $logEntry = $show->streamerLogEntry;

        if (!$logEntry) {
            \Filament\Notifications\Notification::make()
                ->title("Error")
                ->body("No log entry found for this show")
                ->danger()
                ->send();
            return;
        }

        // Reset submitted_at and reviewed_at to allow re-editing, but keep other fields
        $logEntry->update([
            'status' => 'pending',
            'submitted_at' => null,
            'reviewed_by' => null,
            'reviewed_at' => null,
            'locked_at' => null,
        ]);

        // Use model method to set rejection status and notify streamer
        $logEntry->rejectByAdmin('Admin requested changes to your submission.');

        \Filament\Notifications\Notification::make()
            ->title("✓ Change request sent")
            ->body("Notification sent to " . $show->streamers->count() . " streamer(s)")
            ->success()
            ->send();

        // Reload the page to reflect updated form status
        $this->redirectRoute('filament.admin.pages.shows-overview');
    }
}
