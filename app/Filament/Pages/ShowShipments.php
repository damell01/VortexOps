<?php

namespace App\Filament\Pages;

use App\Filament\Resources\ShipmentResource;
use App\Filament\Resources\ShowResource;
use App\Models\Show;
use App\Support\AdminModules;
use Filament\Panel;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;

class ShowShipments extends Page
{
    use \App\Filament\Concerns\HasAdminNavVisibility;

    protected static string $moduleSlug = 'streams';
    protected static ?string $title = 'Show Shipments';

    public string $searchQuery = '';
    public string $filterDelivery = 'all';
    public string $sortBy = 'date';

    public function getView(): string
    {
        return 'filament.pages.show-shipments';
    }

    public function getSubheading(): ?string
    {
        return 'Choose a show first, then open its complete Whatnot shipment list.';
    }

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-truck';
    }

    public static function getNavigationLabel(): string
    {
        return 'Show Shipments';
    }

    public static function getNavigationGroup(): ?string
    {
        return AdminModules::navigationGroupFor('streams');
    }

    public static function getNavigationSort(): ?int
    {
        return 21;
    }

    public static function getSlug(?Panel $panel = null): string
    {
        return 'show-shipments';
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();
        return AdminModules::isEnabled('streams') && ($user?->isAdmin() || $user?->isStreamer());
    }

    #[Computed]
    public function shows(): Collection
    {
        $user = auth()->user();

        $query = Show::query()
            ->inChannelContext()
            ->whereHas('shipments')
            ->with(['streamers', 'channel'])
            ->withCount('shipments')
            ->withCount([
                'shipments as pending_shipments_count' => fn ($q) => $q
                    ->whereRaw("LOWER(COALESCE(status, '')) <> 'delivered'"),
                'shipments as delivered_shipments_count' => fn ($q) => $q
                    ->whereRaw("LOWER(COALESCE(status, '')) = 'delivered'"),
            ])
            ->withSum('shipments', 'shipping_cost');

        if ($user?->isStreamer() && ! $user?->isAdmin()) {
            $streamerId = $user->streamer?->id ?? 0;
            $query->whereHas('streamers', fn ($q) => $q->where('streamers.id', $streamerId));
        }

        if ($this->searchQuery !== '') {
            $needle = trim($this->searchQuery);
            $query->where(function ($q) use ($needle) {
                $q->where('title', 'like', "%{$needle}%")
                    ->orWhere('whatnot_show_id', 'like', "%{$needle}%")
                    ->orWhereHas('streamers', fn ($s) => $s->where('name', 'like', "%{$needle}%"));
            });
        }

        if ($this->filterDelivery === 'open') {
            $query->whereHas('shipments', fn ($q) => $q
                ->whereRaw("LOWER(COALESCE(status, '')) <> 'delivered'"));
        } elseif ($this->filterDelivery === 'delivered') {
            $query->whereDoesntHave('shipments', fn ($q) => $q
                ->whereRaw("LOWER(COALESCE(status, '')) <> 'delivered'"));
        }

        if ($this->sortBy === 'shipments') {
            $query->orderByDesc('shipments_count')->orderByDesc('show_date');
        } elseif ($this->sortBy === 'open') {
            $query->orderByDesc('pending_shipments_count')->orderByDesc('show_date');
        } else {
            $query->orderByDesc('show_date')->orderByDesc('start_time');
        }

        return $query->limit(250)->get();
    }

    public function shipmentsUrl(int $showId): string
    {
        return ShipmentResource::getUrl('index', [
            'tableFilters[show_id][value]' => $showId,
        ]);
    }

    public function showUrl(int $showId): string
    {
        return ShowResource::getUrl('view', ['record' => $showId]);
    }
}
