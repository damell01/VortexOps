<?php

namespace App\Services;

use App\Models\DeductionRequest;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\WhatnotShow;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ReconciliationService
{
    public function __construct(private InventoryService $inventoryService) {}

    public function approve(DeductionRequest $request): void
    {
        if ($request->status !== 'pending') {
            throw new \RuntimeException("Cannot approve a deduction that is not pending.");
        }

        DB::transaction(function () use ($request) {
            $request->update([
                'status'      => 'approved',
                'reviewed_by' => Auth::id(),
                'reviewed_at' => now(),
            ]);
        });
    }

    public function reject(DeductionRequest $request, string $reason): void
    {
        if ($request->status !== 'pending') {
            throw new \RuntimeException("Cannot reject a deduction that is not pending.");
        }

        $request->update([
            'status'           => 'rejected',
            'rejection_reason' => $reason,
            'reviewed_by'      => Auth::id(),
            'reviewed_at'      => now(),
        ]);
    }

    public function executeApproved(WhatnotShow $show): int
    {
        $executed = 0;

        foreach ($show->deductionRequests()->where('status', 'approved')->get() as $request) {
            try {
                DB::transaction(function () use ($request, &$executed) {
                    $item     = $request->inventoryItem;
                    $location = $request->location;

                    $movement = $this->inventoryService->deductStock(
                        $item,
                        $location,
                        (float) $request->quantity,
                        "Sale deduction — Show #{$request->whatnot_show_id}",
                        $request->id
                    );

                    $request->update([
                        'status'               => 'executed',
                        'inventory_movement_id' => $movement->id,
                    ]);

                    $executed++;
                });
            } catch (\RuntimeException $e) {
                // Log insufficient-stock errors but don't stop the loop
                \Illuminate\Support\Facades\Log::warning("Deduction #{$request->id} failed: " . $e->getMessage());
            }
        }

        $allDone = !$show->deductionRequests()->whereIn('status', ['pending', 'approved'])->exists();
        if ($allDone) {
            $show->update(['status' => 'reconciled']);
        }

        return $executed;
    }

    public function bulkApprove(WhatnotShow $show): int
    {
        $count = $show->deductionRequests()
            ->where('status', 'pending')
            ->update([
                'status'      => 'approved',
                'reviewed_by' => Auth::id(),
                'reviewed_at' => now(),
            ]);

        return $count;
    }

    public function getStockAvailable(DeductionRequest $request): float
    {
        $stock = \App\Models\InventoryStock::where('inventory_item_id', $request->inventory_item_id)
            ->where('inventory_location_id', $request->inventory_location_id)
            ->value('quantity');

        return (float) ($stock ?? 0);
    }
}
