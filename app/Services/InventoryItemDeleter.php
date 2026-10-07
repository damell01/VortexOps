<?php

namespace App\Services;

use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use Illuminate\Support\Facades\DB;

/**
 * Deleting an inventory item, safely.
 *
 * Items are soft-deleted, so a mistake is one Restore away. An item that
 * still holds stock is only deleted when the person chooses to write that
 * stock off: each location is set to zero through InventoryService, so the
 * inventory log shows exactly what left and why, and FIFO cost lots are
 * consumed like any other shrinkage. Nothing disappears silently.
 */
class InventoryItemDeleter
{
    public function __construct(private InventoryService $inventory) {}

    /** Units on hand across every location. */
    public function onHand(InventoryItem $item): float
    {
        return (float) $item->stock()->where('quantity', '>', 0)->sum('quantity');
    }

    /** @return array<string, float> location name => units, for the confirmation */
    public function stockByLocation(InventoryItem $item): array
    {
        return $item->stock()->where('quantity', '>', 0)->with('location:id,name')->get()
            ->mapWithKeys(fn ($s) => [($s->location?->name ?? 'Unknown location') => (float) $s->quantity])->all();
    }

    /**
     * @return float units written off
     *
     * @throws \DomainException when the item holds stock and write-off was not chosen
     */
    public function delete(InventoryItem $item, bool $writeOffStock = false, ?string $reason = null): float
    {
        $onHand = $this->onHand($item);
        if ($onHand > 0 && ! $writeOffStock) {
            throw new \DomainException("{$item->name} still has ".rtrim(rtrim(number_format($onHand, 2), '0'), '.').' on hand. Write it off to delete the item.');
        }

        return DB::transaction(function () use ($item, $reason, $onHand) {
            // Written off before the delete: stock rows of a deleted item are
            // hidden by InventoryStock's live-product scope, so afterwards
            // there would be nothing left to zero.
            if ($onHand > 0) {
                $note = 'Written off — item deleted'.(filled($reason) ? ': '.trim($reason) : '');
                foreach ($item->stock()->where('quantity', '>', 0)->get() as $stock) {
                    $location = InventoryLocation::find($stock->inventory_location_id);
                    if ($location) $this->inventory->adjustStock($item, $location, 0, $note, 'adjustment');
                }
            }

            $item->delete();

            activity('inventory')->performedOn($item)->causedBy(auth()->user())
                ->withProperties(['written_off_units' => $onHand, 'reason' => $reason])
                ->log('Inventory item deleted');

            return $onHand;
        });
    }

    public function restore(int $id): ?InventoryItem
    {
        $item = InventoryItem::onlyTrashed()->find($id);
        if (! $item) return null;

        $item->restore();
        activity('inventory')->performedOn($item)->causedBy(auth()->user())->log('Inventory item restored');

        return $item;
    }
}
