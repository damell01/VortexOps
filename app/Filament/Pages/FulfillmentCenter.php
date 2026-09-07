<?php

namespace App\Filament\Pages;

use App\Filament\Resources\ShowResource;
use App\Models\Shipment;
use App\Support\AdminModules;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;

class FulfillmentCenter extends Page
{
    use \App\Filament\Concerns\HasAdminNavVisibility;

    protected static string $moduleSlug = 'fulfillment';
    protected static ?string $title = 'Fulfillment Center';
    protected static ?string $navigationLabel = 'Fulfillment Center';
    protected static ?string $slug = 'fulfillment-center';

    #[Url(as: 'tab')]
    public string $tab = 'queue';

    #[Url(as: 'q')]
    public string $searchQuery = '';

    #[Url(as: 'status')]
    public string $statusFilter = 'all';

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-archive-box-arrow-down';
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return AdminModules::navigationGroupFor('fulfillment');
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
        return AdminModules::isEnabled('fulfillment')
            && ($user?->isAdmin() || $user?->isFulfillment() || $user?->isFulfillmentAdmin());
    }

    public function getSubheading(): ?string
    {
        return 'Pick, pack, resolve shipment exceptions, and close out fulfillment from one workspace.';
    }

    public function getView(): string
    {
        return 'filament.pages.fulfillment-center';
    }

    public function setTab(string $tab): void
    {
        if (in_array($tab, ['queue', 'exceptions', 'history'], true)) {
            $this->tab = $tab;
            unset($this->shipments, $this->summary);
        }
    }

    protected function scopedQuery(): Builder
    {
        $query = Shipment::query()
            ->with(['show.streamers', 'show.channel'])
            ->whereHas('show', fn (Builder $q) => $q->inChannelContext());

        $user = auth()->user();
        if ($user?->isFulfillment() && ! $user?->isFulfillmentAdmin() && ! $user?->isAdmin()) {
            $query->whereHas('show.fulfillmentUsers', fn (Builder $q) => $q->where('users.id', $user->id));
        }

        return $query;
    }

    protected function applySearch(Builder $query): Builder
    {
        $needle = trim($this->searchQuery);
        if ($needle === '') {
            return $query;
        }

        return $query->where(function (Builder $q) use ($needle) {
            $q->where('buyer_username', 'like', "%{$needle}%")
                ->orWhere('whatnot_order_id', 'like', "%{$needle}%")
                ->orWhere('tracking_number', 'like', "%{$needle}%")
                ->orWhereHas('show', fn (Builder $show) => $show
                    ->where('title', 'like', "%{$needle}%")
                    ->orWhere('whatnot_show_id', 'like', "%{$needle}%"));
        });
    }

    protected function applyTab(Builder $query): Builder
    {
        return match ($this->tab) {
            'history' => $query->whereIn('status', ['delivered', 'returned']),
            'exceptions' => $query->where(function (Builder $q) {
                $q->where('status', 'returned')
                    ->orWhereNull('buyer_username')
                    ->orWhere('buyer_username', '')
                    ->orWhere('item_count', '<=', 0)
                    ->orWhere(function (Builder $tracking) {
                        $tracking->whereIn('status', ['shipped', 'in_transit', 'delivered'])
                            ->where(function (Builder $missing) {
                                $missing->whereNull('tracking_number')->orWhere('tracking_number', '');
                            });
                    });
            }),
            default => $query->whereNotIn('status', ['delivered', 'returned']),
        };
    }

    #[Computed]
    public function shipments(): Collection
    {
        $query = $this->applySearch($this->applyTab($this->scopedQuery()));

        if ($this->statusFilter !== 'all') {
            $query->where('status', $this->statusFilter);
        }

        return $query
            ->orderByRaw("CASE WHEN LOWER(COALESCE(status,'')) IN ('packed','ready_to_ship') THEN 0 WHEN LOWER(COALESCE(status,'')) IN ('shipped','in_transit') THEN 2 ELSE 1 END")
            ->orderByDesc('created_at_whatnot')
            ->orderByDesc('id')
            ->limit(500)
            ->get();
    }

    #[Computed]
    public function summary(): array
    {
        $base = $this->scopedQuery();

        $total = (clone $base)->count();
        $ready = (clone $base)->whereIn('status', ['ready_to_ship', 'packed'])->count();
        $inTransit = (clone $base)->whereIn('status', ['shipped', 'in_transit'])->count();
        $delivered = (clone $base)->where('status', 'delivered')->count();
        $open = (clone $base)->whereNotIn('status', ['delivered', 'returned'])->count();
        $unitsOpen = (int) (clone $base)->whereNotIn('status', ['delivered', 'returned'])->sum('item_count');

        $exceptionQuery = $this->applyTabForExceptions(clone $base);
        $exceptions = $exceptionQuery->count();

        return compact('total', 'ready', 'inTransit', 'delivered', 'open', 'unitsOpen', 'exceptions');
    }

    protected function applyTabForExceptions(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->where('status', 'returned')
                ->orWhereNull('buyer_username')
                ->orWhere('buyer_username', '')
                ->orWhere('item_count', '<=', 0)
                ->orWhere(function (Builder $tracking) {
                    $tracking->whereIn('status', ['shipped', 'in_transit', 'delivered'])
                        ->where(function (Builder $missing) {
                            $missing->whereNull('tracking_number')->orWhere('tracking_number', '');
                        });
                });
        });
    }

    public function markPacked(int $shipmentId): void
    {
        $this->updateShipmentStatus($shipmentId, 'packed', 'Shipment marked packed');
    }

    public function markReady(int $shipmentId): void
    {
        $this->updateShipmentStatus($shipmentId, 'ready_to_ship', 'Shipment ready to ship');
    }

    protected function updateShipmentStatus(int $shipmentId, string $status, string $message): void
    {
        $shipment = $this->scopedQuery()->whereKey($shipmentId)->firstOrFail();
        $shipment->status = $status;
        $shipment->save();

        unset($this->shipments, $this->summary);
        Notification::make()->title($message)->success()->send();
    }

    public function showUrl(?int $showId): ?string
    {
        return $showId ? ShowResource::getUrl('view', ['record' => $showId]) : null;
    }
}
