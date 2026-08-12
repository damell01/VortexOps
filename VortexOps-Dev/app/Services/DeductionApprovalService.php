<?php

namespace App\Services;

use App\Jobs\NotifyShowReconciled;
use App\Models\DeductionRequest;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DeductionApprovalService
{
    public function __construct(
        private InventoryService $inventory,
        private PayoutService $payouts,
    ) {}

    public function approve(DeductionRequest $request): void
    {
        // Bulk-load relations before entering the transaction to avoid N+1 queries under lock
        $request->loadMissing('lines');
        $lineItemIds    = $request->lines->pluck('inventory_item_id')->filter()->unique()->values();
        $lineLocationIds = $request->lines->pluck('inventory_location_id')->filter()->unique()->values();
        $itemsById      = InventoryItem::whereIn('id', $lineItemIds)->get()->keyBy('id');
        $locationsById  = InventoryLocation::whereIn('id', $lineLocationIds)->get()->keyBy('id');

        DB::transaction(function () use ($request, $itemsById, $locationsById) {
            // Re-fetch with row lock to prevent double-approval from concurrent clicks
            $locked = DeductionRequest::lockForUpdate()->findOrFail($request->id);
            if (in_array($locked->status, ['processed', 'rejected'])) {
                return;
            }

            $userId = Auth::id();
            $now    = now();

            foreach ($request->lines as $line) {
                if ((float) $line->quantity_approved <= 0) {
                    continue;
                }

                $item     = $itemsById->get($line->inventory_item_id)
                    ?? InventoryItem::findOrFail($line->inventory_item_id);
                $location = $locationsById->get($line->inventory_location_id)
                    ?? InventoryLocation::findOrFail($line->inventory_location_id);

                $this->inventory->deductStock(
                    item:        $item,
                    location:    $location,
                    quantity:    (float) $line->quantity_approved,
                    reason:      "Approved deduction for show #{$request->show_id}",
                    referenceId: $request->id,
                );

                $line->line_total = round((float) $line->quantity_approved * (float) $line->unit_cost_snapshot, 2);
                $line->save();
            }

            $request->update([
                'status'       => 'processed',
                'approved_by'  => $userId,
                'approved_at'  => $now,
                'processed_by' => $userId,
                'processed_at' => $now,
            ]);

            $show = $request->show;
            $show->update(['status' => 'reconciled']);
            $this->payouts->calculateForShow($show->fresh(['streamers']));

            NotifyShowReconciled::dispatch($show->id)->afterCommit();
        });
    }
}
