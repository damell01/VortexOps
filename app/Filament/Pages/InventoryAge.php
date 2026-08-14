<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HasModuleAccess;
use App\Models\InventoryLocation;
use App\Support\AdminModules;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;

/**
 * How long the stock on hand has been sitting.
 *
 * Age is measured from each product's most recent receipt — the last time
 * units of it came in. That is deliberately crude: a SKU restocked several
 * times has no single age, and picking the latest receipt means a topped-up
 * line reads as young even if some of its units are old. The alternative
 * (bucketing each remaining unit against the receipts that brought it in,
 * oldest first) needs per-unit lot tracking to be honest about it.
 */
class InventoryAge extends Page
{
    use HasModuleAccess;

    protected static string $moduleSlug = 'inventory';

    protected static ?string $title = 'Inventory Age';

    protected static ?string $navigationLabel = 'Inventory Age';

    /** Selected location, or '' for every location the viewer can see. */
    public string $locationId = '';

    /** Bucket definitions: label, upper bound in days, risk wording, tone. */
    protected const BUCKETS = [
        ['key' => 'fresh',   'label' => '0 – 30 Days',  'max' => 30,   'risk' => 'Healthy',   'tone' => 'green'],
        ['key' => 'monitor', 'label' => '31 – 60 Days', 'max' => 60,   'risk' => 'Monitor',   'tone' => 'amber'],
        ['key' => 'at_risk', 'label' => '61 – 90 Days', 'max' => 90,   'risk' => 'At Risk',   'tone' => 'orange'],
        ['key' => 'stale',   'label' => '90+ Days',     'max' => null, 'risk' => 'High Risk', 'tone' => 'red'],
    ];

    public function getView(): string
    {
        return 'filament.pages.inventory-age';
    }

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-clock';
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return AdminModules::navigationGroupFor('inventory');
    }

    public static function getNavigationSort(): ?int
    {
        return 4;
    }

    protected static function passesModuleAccessCheck(): bool
    {
        $user = auth()->user();

        return ($user?->isAdmin() || $user?->isOwner() || $user?->isStreamer()) ?? false;
    }

    public function getSubheading(): ?string
    {
        return 'Understand how long items have been in stock.';
    }

    /** Locations the viewer may look at — streamers see only their own. */
    public function getLocationOptions(): array
    {
        return ['' => 'All Locations'] + $this->accessibleLocations()
            ->pluck('name', 'id')
            ->map(fn ($name) => (string) $name)
            ->toArray();
    }

    protected function accessibleLocations()
    {
        $user = auth()->user();

        if ($user?->isAdmin() || $user?->isOwner()) {
            return InventoryLocation::where('status', 'active')->orderBy('name')->get();
        }

        if ($user?->isStreamer() && $user->streamer) {
            return $user->streamer->inventoryLocations()->orderBy('name')->get();
        }

        return collect();
    }

    /**
     * Stock grouped into age buckets.
     *
     * @return array{buckets: array, total_value: float, total_units: float, has_data: bool}
     */
    public function getBuckets(): array
    {
        $locationIds = $this->accessibleLocations()->pluck('id');

        if ($this->locationId !== '') {
            $locationIds = $locationIds->intersect([(int) $this->locationId]);
        }

        if ($locationIds->isEmpty()) {
            return ['buckets' => $this->emptyBuckets(), 'total_value' => 0.0, 'total_units' => 0.0, 'has_data' => false];
        }

        // Last receipt per product: a movement into a location with no source
        // is stock arriving, as opposed to a transfer between locations.
        $lastReceipt = DB::table('inventory_movements')
            ->selectRaw('inventory_item_id, MAX(created_at) as received_at')
            ->whereNotNull('to_location_id')
            ->whereNull('from_location_id')
            ->whereIn('to_location_id', $locationIds)
            ->groupBy('inventory_item_id');

        $rows = DB::table('inventory_stock')
            ->join('products', 'products.id', '=', 'inventory_stock.inventory_item_id')
            ->leftJoinSub($lastReceipt, 'r', 'r.inventory_item_id', '=', 'inventory_stock.inventory_item_id')
            ->whereIn('inventory_stock.inventory_location_id', $locationIds)
            ->where('inventory_stock.quantity', '>', 0)
            ->selectRaw('inventory_stock.quantity as qty')
            ->selectRaw('COALESCE(products.average_cost, 0) as unit_cost')
            // No receipt on record (opening balance, imported stock) falls back
            // to when the product was created rather than dropping out.
            ->selectRaw('COALESCE(r.received_at, products.created_at) as received_at')
            ->get();

        $buckets = $this->emptyBuckets();
        $totalValue = 0.0;
        $totalUnits = 0.0;
        $now = now();

        foreach ($rows as $row) {
            // Carbon 3 diffs are signed: $now->diffInDays($past) is negative,
            // so every bucket test passed the first bound and all stock read
            // as 0-30 days regardless of age. Diff from the past date forward.
            $days = $row->received_at
                ? (int) floor(\Carbon\Carbon::parse($row->received_at)->diffInDays($now))
                : 0;
            $qty   = (float) $row->qty;
            $value = $qty * (float) $row->unit_cost;

            $key = $this->bucketFor((int) $days);
            $buckets[$key]['units'] += $qty;
            $buckets[$key]['value'] += $value;

            $totalUnits += $qty;
            $totalValue += $value;
        }

        // Percentages are of value, which is what the share is read as.
        foreach ($buckets as $key => $bucket) {
            $buckets[$key]['pct'] = $totalValue > 0
                ? round(($bucket['value'] / $totalValue) * 100, 1)
                : 0.0;
        }

        return [
            'buckets'     => array_values($buckets),
            'total_value' => $totalValue,
            'total_units' => $totalUnits,
            'has_data'    => $totalUnits > 0,
        ];
    }

    protected function bucketFor(int $days): string
    {
        foreach (self::BUCKETS as $bucket) {
            if ($bucket['max'] === null || $days <= $bucket['max']) {
                return $bucket['key'];
            }
        }

        return 'stale';
    }

    protected function emptyBuckets(): array
    {
        $out = [];

        foreach (self::BUCKETS as $bucket) {
            $out[$bucket['key']] = $bucket + ['units' => 0.0, 'value' => 0.0, 'pct' => 0.0];
        }

        return $out;
    }
}
