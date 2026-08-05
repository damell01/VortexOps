<?php

namespace App\Filament\Pages;

use App\Models\Show;
use App\Models\StreamerLogEntry;
use App\Models\Streamer;
use App\Support\AdminModules;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;

class StreamLogs extends Page
{
    protected static string $moduleSlug = 'streams';
    protected static ?string $title = 'Stream Logs';

    public string $filterStatus = 'all'; // all, active, completed, closed
    public string $filterStreamer = '';
    public string $searchQuery = '';
    public string $sortBy = 'date';

    public function getSubheading(): ?string
    {
        return 'View stream activity, end-of-stream submissions, and streamer reports.';
    }

    public function getView(): string
    {
        return 'filament.pages.stream-logs';
    }

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-video-camera';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Streams';
    }

    public static function getNavigationSort(): ?int
    {
        return 39;
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();
        return AdminModules::isEnabled('streams')
            && ($user?->isAdmin() || $user?->isStreamer());
    }

    public static function shouldRegisterNavigation(): bool
    {
        return true;
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
    public function streams(): Collection
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
            ->with(['streamers', 'showOrders', 'streamerLogEntries'])
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
}
