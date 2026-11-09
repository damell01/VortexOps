<?php

namespace App\Filament\Pages;

use App\Models\Shipment;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;

class ShipmentOverview extends Page
{
    protected static ?string $title = 'Shipment Tracking';
    protected static ?string $slug = 'shipment-tracking';
    protected static bool $shouldRegisterNavigation = false;

    public string $search = '';
    public string $status = 'all';
    public int $days = 30;

    public static function canAccess(): bool
    {
        $user = auth()->user();
        return (bool) ($user && ($user->isOwner() || $user->isAdmin() || $user->isFulfillmentAdmin()));
    }

    public function getView(): string
    {
        return 'filament.pages.shipment-overview';
    }

    public function shipmentHistory(int $shipmentId): \Illuminate\Support\Collection
    {
        abort_unless(static::canAccess(), 403);
        $shipment = Shipment::query()->findOrFail($shipmentId);
        // A timeline is read-only and scoped to an existing shipment.
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

    public function dashboardData(): array
    {
        abort_unless(static::canAccess(), 403);
        $base = Shipment::query()->whereHas('show', fn ($query) =>
            $query->where('show_date', '>=', now()->subDays(max(1, min($this->days, 365)))->toDateString()));
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
