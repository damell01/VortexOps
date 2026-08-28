<?php

namespace App\Filament\Pages;

use App\Filament\Resources\ShowResource;
use App\Models\Show;
use App\Support\AdminModules;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;

class StreamsOverview extends Page
{
    use \App\Filament\Concerns\HasAdminNavVisibility;

    protected static string $moduleSlug = 'streams';
    protected static ?string $title = 'Streams Overview';
    protected static ?string $navigationLabel = 'Overview';
    protected static ?string $slug = 'streams-overview';

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-squares-2x2';
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return AdminModules::navigationGroupFor('streams');
    }

    public static function getNavigationSort(): ?int
    {
        return 1;
    }

    public static function canAccess(): bool
    {
        if (\App\Support\RoleAccess::grants(static::class)) {
            return true;
        }

        $user = auth()->user();
        return AdminModules::isEnabled('streams') && ($user?->isAdmin() || $user?->isStreamer());
    }

    public function getSubheading(): ?string
    {
        return 'Shows, submissions, revenue, shipments, and recent activity in one place.';
    }

    public function getView(): string
    {
        return 'filament.pages.streams-overview';
    }

    protected function scopedShowsQuery()
    {
        $query = Show::query()->inChannelContext();
        $user = auth()->user();

        if ($user?->isStreamer() && ! $user?->isAdmin() && $user->streamer) {
            $query->whereHas('streamers', fn ($q) => $q->where('streamers.id', $user->streamer->id));
        }

        return $query;
    }

    #[Computed]
    public function streamSnapshot(): array
    {
        $base = $this->scopedShowsQuery();
        $from = now()->subDays(30)->toDateString();
        $to = today()->toDateString();

        $total30 = (clone $base)->whereDate('show_date', '>=', $from)->count();
        $upcoming = (clone $base)->whereDate('show_date', '>', today())->count();
        $needsSubmission = (clone $base)
            ->whereDate('show_date', '<=', today())
            ->whereDoesntHave('streamerLogEntry')
            ->whereNotIn('status', ['closed', 'cancelled'])
            ->count();
        $grossRevenue30 = (float) (clone $base)
            ->whereBetween('show_date', [$from, $to])
            ->sum('gross_revenue');
        $netRevenue30 = (float) (clone $base)
            ->whereBetween('show_date', [$from, $to])
            ->sum('whatnot_net');

        $openShipments = (int) (clone $base)
            ->withCount([
                'shipments as open_shipments_count' => fn ($q) => $q->whereRaw("LOWER(COALESCE(status, '')) <> 'delivered'"),
            ])
            ->get()
            ->sum('open_shipments_count');

        return compact('total30', 'upcoming', 'needsSubmission', 'grossRevenue30', 'netRevenue30', 'openShipments');
    }

    #[Computed]
    public function upcomingShows(): Collection
    {
        return $this->scopedShowsQuery()
            ->whereDate('show_date', '>', today())
            ->with('streamers')
            ->orderBy('show_date')
            ->orderBy('start_time')
            ->limit(5)
            ->get();
    }

    #[Computed]
    public function recentShows(): Collection
    {
        return $this->scopedShowsQuery()
            ->with(['streamers', 'streamerLogEntry'])
            ->withCount(['orders', 'shipments'])
            ->withCount([
                'shipments as open_shipments_count' => fn ($q) => $q->whereRaw("LOWER(COALESCE(status, '')) <> 'delivered'"),
            ])
            ->orderByDesc('show_date')
            ->orderByDesc('start_time')
            ->limit(8)
            ->get();
    }

    #[Computed]
    public function attentionShows(): Collection
    {
        return $this->scopedShowsQuery()
            ->whereDate('show_date', '<=', today())
            ->whereDoesntHave('streamerLogEntry')
            ->whereNotIn('status', ['closed', 'cancelled'])
            ->with('streamers')
            ->orderByDesc('show_date')
            ->limit(5)
            ->get();
    }

    #[Computed]
    public function recentActivity(): Collection
    {
        return $this->scopedShowsQuery()
            ->with(['streamers', 'streamerLogEntry'])
            ->whereNotNull('status_changed_at')
            ->orderByDesc('status_changed_at')
            ->limit(6)
            ->get();
    }

    public function showUrl(int $id): string
    {
        return ShowResource::getUrl('view', ['record' => $id]);
    }

    public function showsUrl(): string { return Shows::getUrl(); }
    public function shipmentsUrl(): string { return ShowShipments::getUrl(); }
    public function importerUrl(): string { return WhatnotScraperPage::getUrl(); }
    public function syncUrl(): string { return WhatnotSyncPage::getUrl(); }
    public function statusUrl(): string { return ShowStatusBoard::getUrl(); }
}
