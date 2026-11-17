<?php

namespace App\Filament\Pages;

use App\Models\Shipment;
use App\Support\ShipmentTrackingAccess;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ShipmentOverview extends Page
{
    protected static ?string $title = 'Shipment Tracking';
    protected static ?string $slug = 'shipment-tracking';
    protected static bool $shouldRegisterNavigation = false;

    public string $search = '';
    public string $status = 'all';
    public int $days = 30;
    public string $showId = 'all';
    public ?int $selectedShipmentId = null;

    /**
     * Shipment history is visible before packing/report approval. The query
     * still honors the user's selected channel and date/show filters.
     */
    private function visibleShipments(): Builder
    {
        $cutoff = now()->subDays(max(1, min($this->days, 365)))->toDateString();
        $query = ShipmentTrackingAccess::shipments()
            ->whereHas('show', fn (Builder $show) => $show->where('show_date', '>=', $cutoff));

        if ($this->showId !== 'all') {
            abort_unless(ctype_digit($this->showId), 422);
            $query->where('show_id', (int) $this->showId);
        }

        return $query;
    }

    public function selectShipment(int $id): void
    {
        abort_unless(static::canAccess(), 403);
        abort_unless($this->visibleShipments()->whereKey($id)->exists(), 404);
        $this->selectedShipmentId = $id;
    }

    public function selectedShipment(): ?Shipment
    {
        abort_unless(static::canAccess(), 403);

        return $this->selectedShipmentId
            ? $this->visibleShipments()->with('show:id,title,show_date')->find($this->selectedShipmentId)
            : null;
    }

    public static function canAccess(): bool
    {
        return ShipmentTrackingAccess::canView(auth()->user());
    }

    /** Shipping & Labels requires a packing-ready show; tracking does not. */
    public function canOpenShippingDesk(int $showId): bool
    {
        return static::canAccess()
            && \App\Support\FulfillmentAccess::moduleEnabled()
            && \App\Filament\Resources\FulfillmentResource::getEloquentQuery()->whereKey($showId)->exists();
    }

    public function getView(): string
    {
        return 'filament.pages.shipment-overview';
    }

    public function shipmentHistory(int $shipmentId): \Illuminate\Support\Collection
    {
        abort_unless(static::canAccess(), 403);
        $shipment = $this->visibleShipments()->findOrFail($shipmentId);
        // A timeline is read-only and scoped to an authorized shipment.
        return DB::table('fulfillment_shipment_events')
            ->where('shipment_id', $shipment->id)
            ->orderByDesc('observed_at')->limit(50)->get();
    }

    /** The summary boxes. Every stored status (API codes or scraped text) falls into one. */
    public const BUCKETS = ['created' => 'Created', 'label' => 'Label ready', 'in_transit' => 'In transit',
        'shipped' => 'Shipped', 'delivered' => 'Delivered', 'failed' => 'Failed', 'cancelled' => 'Cancelled'];

    /**
     * Group a stored shipment status. The Seller API importer stores
     * pending / label_created / shipped / in_transit / delivered / failed /
     * cancelled; the scraper stores Whatnot's text ("Label Created",
     * "In Transit", "Out for delivery"...).
     */
    public static function bucket(?string $status): string
    {
        $s = strtolower(trim((string) preg_replace('/[\s-]+/', '_', trim((string) $status))));

        return match (true) {
            $s === '' || in_array($s, ['pending', 'created', 'awaiting_label', 'unshipped', 'not_shipped', 'processing'], true) => 'created',
            str_contains($s, 'cancel') => 'cancelled',
            str_contains($s, 'fail') || str_contains($s, 'exception') || str_contains($s, 'return') || str_contains($s, 'undeliver') || str_contains($s, 'lost') || str_contains($s, 'stuck') => 'failed',
            str_contains($s, 'out_for_delivery') || str_contains($s, 'transit') || str_contains($s, 'accepted') => 'in_transit',
            str_contains($s, 'deliver') => 'delivered',
            str_contains($s, 'label') => 'label',
            str_contains($s, 'ship') || str_contains($s, 'dropped') || str_contains($s, 'picked_up') => 'shipped',
            default => 'created',
        };
    }

    /** Recent shows are selected through the same fulfillment-scoped query as Shipping & Labels. */
    public function recentShows(): \Illuminate\Support\Collection
    {
        abort_unless(static::canAccess(), 403);

        return ShipmentTrackingAccess::shows()
            ->where('show_date', '>=', now()->subDays(max(1, min($this->days, 365)))->toDateString())
            ->whereHas('shipments')
            ->withCount('shipments')
            ->orderByDesc('show_date')
            ->limit(60)
            ->get(['id', 'title', 'show_date', 'whatnot_channel_id']);
    }

    /** Fetch fresh shipment data through the existing authorized Whatnot refresh service. */
    public function refreshSelectedShow(): void
    {
        abort_unless(static::canAccess(), 403);
        abort_unless($this->showId !== 'all' && ctype_digit($this->showId), 422);
        $show = ShipmentTrackingAccess::shows()
            ->where('show_date', '>=', now()->subDays(max(1, min($this->days, 365)))->toDateString())
            ->whereHas('shipments')
            ->findOrFail((int) $this->showId);
        try {
            $result = app(\App\Services\FulfillmentShipmentRefreshService::class)->refresh($show);
            \Filament\Notifications\Notification::make()->title('Shipments refreshed')
                ->body(($result['matched'] ?? 0).' matched; '.($result['updated'] ?? 0).' updated from Whatnot.')
                ->success()->send();
        } catch (\Illuminate\Validation\ValidationException $exception) {
            \Filament\Notifications\Notification::make()->title('Whatnot refresh unavailable')
                ->body(collect($exception->errors())->flatten()->first() ?: 'Check the Whatnot API connection.')
                ->warning()->send();
        } catch (\Throwable $exception) {
            report($exception);
            \Filament\Notifications\Notification::make()->title('Shipment refresh failed')
                ->body('Review Whatnot API permissions and application logs.')->danger()->send();
        }
    }

    public function dashboardData(): array
    {
        abort_unless(static::canAccess(), 403);
        $base = $this->visibleShipments();
        $byStatus = (clone $base)->select('status', DB::raw('COUNT(*) as total'))->groupBy('status')->get();
        $totals = array_fill_keys(array_keys(self::BUCKETS), 0);
        $statusesIn = [];
        foreach ($byStatus as $row) {
            $bucket = self::bucket($row->status);
            $totals[$bucket] += (int) $row->total;
            $statusesIn[$bucket][] = $row->status;
        }
        $wanted = array_key_exists($this->status, self::BUCKETS) ? ($statusesIn[$this->status] ?? []) : null;
        $rows = (clone $base)->with('show:id,title,show_date,whatnot_channel_id')
            ->when(trim($this->search) !== '', function ($query) {
                $term = '%'.addcslashes(trim($this->search), '%_\\').'%';
                $query->where(function ($q) use ($term) {
                    $q->where('buyer_username', 'like', $term)
                        ->orWhere('tracking_number', 'like', $term)
                        ->orWhereHas('show', fn ($show) => $show->where('title', 'like', $term));
                });
            })
            ->when($wanted !== null, fn ($query) => $query->where(function ($q) use ($wanted) {
                $q->whereIn('status', array_values(array_filter($wanted, fn ($v) => $v !== null)) ?: ['__none__']);
                if (in_array(null, $wanted, true)) $q->orWhereNull('status');
            }))
            ->latest('updated_at')->limit(150)->get();
        return compact('rows', 'totals');
    }
}
