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
    public function __construct(private InventoryService $inventory) {}

    public function approve(DeductionRequest $request): void
    {
        DB::transaction(function () use ($request) {
            $userId = Auth::id();
            $now    = now();

            foreach ($request->lines as $line) {
                if ((float) $line->quantity_approved <= 0) {
                    continue;
                }

                $item     = InventoryItem::findOrFail($line->inventory_item_id);
                $location = InventoryLocation::findOrFail($line->inventory_location_id);

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

            NotifyShowReconciled::dispatch($show->id)->afterCommit();
        });
    }
}
