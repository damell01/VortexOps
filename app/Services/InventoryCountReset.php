<?php

namespace App\Services;

use App\Models\InventoryStock;
use App\Models\InventoryItem;
use App\Models\InventoryLot;
use App\Models\User;
use App\Support\AdminModules;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/** Explicit all-stock or selected-product write-off. Adding this service never runs a reset. */
class InventoryCountReset
{
    public const CONFIRMATION = 'RESET ALL STOCK';
    public const SELECTED_CONFIRMATION = 'RESET SELECTED STOCK';

    public static function canReset(?User $user): bool
    {
        return ($user?->isAdmin() ?? false) && AdminModules::isEnabled('inventory')
            && ! \App\Support\NavVisibility::isHiddenForUser(\App\Filament\Resources\InventoryItemResource::class, $user)
            && ! \App\Support\NavVisibility::isReadOnlyForUser(\App\Filament\Resources\InventoryItemResource::class, $user);
    }

    public function reset(User $actor, string $confirmation, string $reason, ?array $productIds = null): array
    {
        abort_unless(self::canReset($actor), 403);
        $data = Validator::make(['confirmation' => $confirmation, 'reason' => trim($reason), 'product_ids' => $productIds], [
            'confirmation' => ['required', Rule::in([$productIds === null ? self::CONFIRMATION : self::SELECTED_CONFIRMATION])],
            'reason' => 'required|string|min:3|max:500',
            'product_ids' => 'nullable|array|min:1',
            'product_ids.*' => ['integer', 'distinct', Rule::exists('products', 'id')->whereNull('deleted_at')],
        ])->validate();

        return DB::transaction(function () use ($actor, $data, $productIds) {
            // Deliberately not in channel/business/search context: the confirmation
            // explicitly covers every location. Selected mode only touches its product IDs.
            $stocks = InventoryStock::where('quantity', '!=', 0)->when($productIds !== null, fn ($q) => $q->whereIn('inventory_item_id', $productIds))->orderBy('inventory_item_id')->orderBy('id')
                ->lockForUpdate()->with('location:id,name')->get();
            $productIdsToReset = $stocks->pluck('inventory_item_id')->unique();
            $lots = InventoryLot::whereIn('product_id', $productIdsToReset)->where('status', InventoryLot::STATUS_ACTIVE)
                ->where('remaining_quantity', '>', 0)->orderBy('id')->lockForUpdate()->get();
            $items = InventoryItem::whereKey($productIdsToReset)->orderBy('id')->lockForUpdate()->get(['id', 'name', 'average_cost'])->keyBy('id');
            $before = $stocks->map(fn ($s) => ['stock_id' => $s->id, 'product_id' => $s->inventory_item_id, 'location_id' => $s->inventory_location_id, 'quantity' => (float) $s->quantity])->all();
            $lotBefore = $lots->map(fn ($l) => ['lot_id' => $l->id, 'product_id' => $l->product_id, 'remaining_quantity' => (float) $l->remaining_quantity])->all();
            $costs = $items->mapWithKeys(fn ($item) => [$item->id => $item->average_cost]);
            $resetId = (string) Str::uuid();
            $summary = ['reset_id' => $resetId, 'scope' => $productIds === null ? 'all' : 'selected', 'selected_product_ids' => $productIds, 'products' => $items->count(), 'rows' => $stocks->count(),
                'locations' => $stocks->pluck('inventory_location_id')->unique()->count(),
                'units_removed' => $stocks->where('quantity', '>', 0)->sum('quantity'),
                'negative_units_corrected' => abs($stocks->where('quantity', '<', 0)->sum('quantity'))];

            foreach ($stocks as $stock) {
                if (! $items->has($stock->inventory_item_id) || ! $stock->location) throw new \RuntimeException('A stock row is missing its product or location. Nothing was reset.');
                app(InventoryService::class)->adjustStock($items[$stock->inventory_item_id], $stock->location, 0,
                    ($productIds === null ? 'All-stock reset ' : 'Selected-stock reset ').$resetId.': '.$data['reason'], 'adjustment', 'inventory_reset', notify: false);
            }
            foreach ($items as $item) {
                // Clear residual FIFO coverage as well, even if legacy lot balances
                // exceeded physical stock. Do not leave costed units after a full reset.
                $remaining = InventoryLot::where('product_id', $item->id)->where('status', InventoryLot::STATUS_ACTIVE)->sum('remaining_quantity');
                app(InventoryLotService::class)->consume($item, (float) $remaining);
                // A count reset does not change the saved price/cost reference.
                $item->forceFill(['average_cost' => $costs[$item->id]])->saveQuietly();
            }
            if ($stocks->isNotEmpty()) {
                activity('inventory')->causedBy($actor)->withProperties($summary + ['reason' => $data['reason'], 'stock_before' => $before, 'lots_before' => $lotBefore])
                    ->log($productIds === null ? 'All inventory counts reset to zero' : 'Selected inventory counts reset to zero');
            }
            return $summary;
        }, 3);
    }
}
