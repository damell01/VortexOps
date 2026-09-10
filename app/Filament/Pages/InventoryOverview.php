<?php

namespace App\Filament\Pages;

use App\Filament\Resources\InventoryItemResource;
use App\Filament\Resources\InventoryLocationResource;
use App\Filament\Resources\PalletResource;
use App\Filament\Resources\VendorResource;
use App\Models\InventoryMovement;
use App\Support\AdminModules;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;

class InventoryOverview extends Page
{
    use \App\Filament\Concerns\HasAdminNavVisibility;

    protected static string $moduleSlug = 'inventory';
    protected static ?string $title = 'Inventory Overview';
    protected static ?string $navigationLabel = 'Overview';
    protected static ?string $slug = 'inventory-overview';

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-squares-2x2';
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return AdminModules::navigationGroupFor('inventory');
    }

    public static function getNavigationSort(): ?int
    {
        return 1;
    }

    public static function canAccess(): bool
    {
        return InventoryItemResource::canAccess();
    }

    public function getSubheading(): ?string
    {
        return 'Track inventory health, recent stock activity, and the work that needs attention.';
    }

    public function getView(): string
    {
        return 'filament.pages.inventory-overview';
    }

    #[Computed]
    public function inventorySnapshot(): array
    {
        $items = InventoryItemResource::getEloquentQuery()
            ->with('preferredVendor')
            ->get();

        $total = $items->count();
        $out = 0;
        $low = 0;
        $noReorder = 0;
        $value = 0.0;

        foreach ($items as $item) {
            $onHand = (float) ($item->stock_sum_quantity ?? 0);
            $value += max(0, $onHand) * $item->effectiveCost();

            if ($onHand <= 0) {
                $out++;
            } elseif ($item->reorder_level !== null && $onHand <= (float) $item->reorder_level) {
                $low++;
            }

            if ($item->reorder_level === null) {
                $noReorder++;
            }
        }

        $in = max(0, $total - $out - $low);
        $pct = fn (int $count): float => $total > 0 ? round(($count / $total) * 100, 1) : 0.0;

        return [
            'total' => $total,
            'in' => $in,
            'low' => $low,
            'out' => $out,
            'no_reorder' => $noReorder,
            'value' => round($value, 2),
            'percentages' => [
                'total' => $total > 0 ? 100.0 : 0.0,
                'in' => $pct($in),
                'low' => $pct($low),
                'out' => $pct($out),
            ],
        ];
    }

    #[Computed]
    public function recentMovements(): Collection
    {
        $movements = InventoryMovement::query()
            ->inChannelContext()
            ->with(['item', 'fromLocation', 'toLocation', 'createdByUser'])
            ->latest()
            // Pull extra raw rows because one pallet receipt may have hundreds
            // of one-case movement rows. The dashboard shows business actions,
            // while the full movement history remains available for audit.
            ->limit(120)
            ->get();

        return $this->collapsePalletReceiptMovements($movements)->take(8)->values();
    }

    #[Computed]
    public function recentRestocks(): Collection
    {
        $movements = InventoryMovement::query()
            ->inChannelContext()
            ->whereIn('movement_type', ['opening', 'return'])
            ->with(['item', 'toLocation', 'createdByUser'])
            ->latest()
            ->limit(80)
            ->get();

        return $this->collapsePalletReceiptMovements($movements)->take(5)->values();
    }

    /**
     * Receiving historically wrote one `opening` movement per case. That is
     * useful as raw audit evidence but terrible dashboard UX: receiving 200
     * single units became 200 separate +1 rows. Collapse only movements that
     * explicitly came from the same pallet/line, item, location and minute.
     * Every unrelated adjustment, sale, return or manual opening remains its
     * own row.
     */
    private function collapsePalletReceiptMovements(Collection $movements): Collection
    {
        $output = collect();
        $groups = [];

        foreach ($movements as $movement) {
            $isPalletReceipt = $movement->movement_type === 'opening'
                && str_starts_with((string) $movement->reason, 'Received via pallet #');

            if (! $isPalletReceipt) {
                $output->push($movement);
                continue;
            }

            $minute = $movement->created_at?->format('Y-m-d H:i') ?? 'unknown';
            $key = implode('|', [
                $movement->inventory_item_id,
                $movement->to_location_id,
                $movement->reason,
                $minute,
            ]);

            if (! isset($groups[$key])) {
                $clone = clone $movement;
                $clone->quantity_before = null;
                $clone->quantity_after = null;
                $clone->quantity = abs((float) $movement->quantity);
                $groups[$key] = $clone;
                $output->push($clone);
                continue;
            }

            $groups[$key]->quantity = (float) $groups[$key]->quantity + abs((float) $movement->quantity);
        }

        return $output->sortByDesc(fn (InventoryMovement $movement) => $movement->created_at?->getTimestamp() ?? 0)->values();
    }

    public function inventoryUrl(?string $stock = null): string
    {
        $url = InventoryItemResource::getUrl('index');
        return $stock ? $url . '?stock=' . urlencode($stock) : $url;
    }

    public function itemUrl(int $id): string
    {
        return InventoryItemResource::getUrl('view', ['record' => $id]);
    }

    public function scanUrl(): string
    {
        return InventoryScanner::getUrl();
    }

    public function receiveUrl(): string
    {
        return PalletResource::getUrl('index');
    }

    public function quickAddUrl(): string
    {
        return InventoryItemResource::getUrl('quick-add');
    }

    public function importUrl(): string
    {
        return ImportInventorySheet::getUrl();
    }

    public function locationsUrl(): string
    {
        return InventoryLocationResource::getUrl('index');
    }

    public function vendorsUrl(): string
    {
        return VendorResource::getUrl('index');
    }
}
