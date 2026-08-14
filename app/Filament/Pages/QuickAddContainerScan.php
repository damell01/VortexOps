<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HasModuleAccess;
use App\Models\InventoryItem;
use App\Models\InventoryItemContent;
use App\Support\AdminModules;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;

/**
 * Scan a case or box, then scan what's inside it.
 *
 * Records the container's contents — the parent/child rows the Contents
 * modal on the inventory list reads. It deliberately does not move stock:
 * receiving is the scanner's job, this is about describing what a container
 * holds so a case can be broken down later.
 */
class QuickAddContainerScan extends Page
{
    use HasModuleAccess;

    protected static string $moduleSlug = 'inventory';

    protected static ?string $title = 'Quick Add (Container Scan)';

    protected static ?string $navigationLabel = 'Container Scan';

    /** 1 = scan container, 2 = scan items, 3 = review. */
    public int $step = 1;

    public string $containerCode = '';

    public ?int $containerId = null;

    public string $itemCode = '';

    /** Scanned contents, keyed by product id: [id, name, sku, qty]. */
    public array $lines = [];

    /** Last lookup failure, shown inline so a bad scan is obvious. */
    public string $lookupError = '';

    public function getView(): string
    {
        return 'filament.pages.quick-add-container-scan';
    }

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-qr-code';
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return AdminModules::navigationGroupFor('inventory');
    }

    public static function getNavigationSort(): ?int
    {
        return 5;
    }

    protected static function passesModuleAccessCheck(): bool
    {
        $user = auth()->user();

        return ($user?->isAdmin() || $user?->isOwner()) ?? false;
    }

    public function getSubheading(): ?string
    {
        return 'Scan a container or box SKU first, then scan the individual items inside it.';
    }

    public function getContainerProperty(): ?InventoryItem
    {
        return $this->containerId ? InventoryItem::find($this->containerId) : null;
    }

    /** @return array{items: int, units: int, value: float} */
    public function getTotalsProperty(): array
    {
        return [
            'items' => count($this->lines),
            'units' => array_sum(array_column($this->lines, 'qty')),
            'value' => array_sum(array_map(
                fn ($line) => (float) $line['unit_cost'] * (int) $line['qty'],
                $this->lines,
            )),
        ];
    }

    /* ── Step 1: the container ─────────────────────────────────────── */

    /** Scanning fires an input event, so the lookup runs without a button. */
    public function updatedContainerCode(): void
    {
        if (strlen(trim($this->containerCode)) >= 3) {
            $this->lookupContainer();
        }
    }

    public function lookupContainer(): void
    {
        $code = trim($this->containerCode);
        $this->lookupError = '';

        if ($code === '') {
            return;
        }

        $item = InventoryItem::findByScan($code);

        if (! $item) {
            $this->lookupError = "Nothing in the catalogue matches “{$code}”.";

            return;
        }

        $this->containerId = $item->getKey();
        $this->containerCode = '';
        $this->step = 2;

        // Pre-load anything already recorded so a second pass tops up the
        // existing contents rather than silently starting from scratch.
        $this->lines = [];

        foreach ($item->childContents()->with('childItem')->get() as $existing) {
            if (! $existing->childItem) {
                continue;
            }

            $this->lines[$existing->child_inventory_item_id] = [
                'id'        => $existing->child_inventory_item_id,
                'name'      => $existing->childItem->name,
                'sku'       => $existing->childItem->sku,
                'unit_cost' => (float) ($existing->childItem->average_cost ?? 0),
                'qty'       => (int) $existing->quantity_per_parent,
            ];
        }
    }

    public function changeContainer(): void
    {
        $this->containerId = null;
        $this->containerCode = '';
        $this->lines = [];
        $this->lookupError = '';
        $this->step = 1;
    }

    /* ── Step 2: what's inside ─────────────────────────────────────── */

    public function updatedItemCode(): void
    {
        if (strlen(trim($this->itemCode)) >= 3) {
            $this->lookupItem();
        }
    }

    public function lookupItem(): void
    {
        $code = trim($this->itemCode);
        $this->lookupError = '';

        if ($code === '') {
            return;
        }

        $item = InventoryItem::findByScan($code);

        if (! $item) {
            $this->lookupError = "Nothing in the catalogue matches “{$code}”.";

            return;
        }

        if ($item->getKey() === $this->containerId) {
            $this->lookupError = 'That is the container itself — scan the items inside it.';

            return;
        }

        // Scanning the same item twice counts it twice rather than erroring;
        // that's the whole point of scanning a case of identical boxes.
        $id = $item->getKey();

        if (isset($this->lines[$id])) {
            $this->lines[$id]['qty']++;
        } else {
            $this->lines[$id] = [
                'id'        => $id,
                'name'      => $item->name,
                'sku'       => $item->sku,
                'unit_cost' => (float) ($item->average_cost ?? 0),
                'qty'       => 1,
            ];
        }

        $this->itemCode = '';
    }

    public function incrementLine(int $id): void
    {
        if (isset($this->lines[$id])) {
            $this->lines[$id]['qty']++;
        }
    }

    public function decrementLine(int $id): void
    {
        if (! isset($this->lines[$id])) {
            return;
        }

        if ($this->lines[$id]['qty'] <= 1) {
            unset($this->lines[$id]);

            return;
        }

        $this->lines[$id]['qty']--;
    }

    public function setLineQty(int $id, $qty): void
    {
        if (! isset($this->lines[$id])) {
            return;
        }

        $qty = max(0, (int) $qty);

        if ($qty === 0) {
            unset($this->lines[$id]);

            return;
        }

        $this->lines[$id]['qty'] = $qty;
    }

    public function removeLine(int $id): void
    {
        unset($this->lines[$id]);
    }

    public function clearAll(): void
    {
        $this->lines = [];
    }

    /* ── Step 3: save ──────────────────────────────────────────────── */

    public function goToStep(int $step): void
    {
        // Can't review an empty container, and can't skip picking one.
        if ($step >= 2 && ! $this->containerId) {
            return;
        }

        if ($step === 3 && $this->lines === []) {
            $this->lookupError = 'Scan at least one item before reviewing.';

            return;
        }

        $this->lookupError = '';
        $this->step = max(1, min(3, $step));
    }

    public function save(): void
    {
        $container = $this->container;

        if (! $container || $this->lines === []) {
            return;
        }

        DB::transaction(function () use ($container) {
            // Marking it a container is what makes the Contents action appear
            // on the inventory list.
            $container->update(['is_container' => true]);

            foreach ($this->lines as $line) {
                // withTrashed(): the table is soft-deleting but its unique
                // index on (parent, child) still counts trashed rows, so a
                // plain updateOrCreate misses the old row and then fails the
                // constraint on insert — removing an item from a container
                // and scanning it back in would 500.
                $content = InventoryItemContent::withTrashed()->firstOrNew([
                    'parent_inventory_item_id' => $container->getKey(),
                    'child_inventory_item_id'  => $line['id'],
                ]);

                $content->quantity_per_parent = $line['qty'];
                $content->created_by = $content->created_by ?? auth()->id();
                $content->deleted_at = null;
                $content->save();
            }
        });

        $totals = $this->totals;

        Notification::make()
            ->title('Container contents saved')
            ->body("{$container->name} now lists {$totals['items']} item(s), {$totals['units']} unit(s) inside.")
            ->success()
            ->send();

        $this->changeContainer();
    }
}
