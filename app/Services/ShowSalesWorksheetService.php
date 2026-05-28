<?php

namespace App\Services;

use App\Models\DeductionRequest;
use App\Models\DeductionRequestLine;
use App\Models\InventoryItem;
use App\Models\Show;
use Illuminate\Support\Facades\DB;

class ShowSalesWorksheetService
{
    /**
     * @param  array<int, array<string, mixed>>  $lines
     */
    public function storeManualSoldItems(Show $show, int $streamerId, array $lines): DeductionRequest
    {
        return DB::transaction(function () use ($show, $streamerId, $lines): DeductionRequest {
            $request = $show->deductionRequests()
                ->whereIn('status', ['draft', 'pending'])
                ->latest('id')
                ->first();

            if (! $request) {
                $request = new DeductionRequest([
                    'show_id' => $show->id,
                ]);
            }

            $request->fill([
                'streamer_id' => $streamerId,
                'status' => 'pending',
                'ai_mapping_notes' => 'Manual sold-item worksheet entered by ops.',
            ])->save();

            $request->lines()->delete();

            foreach ($lines as $lineData) {
                if (blank($lineData['inventory_item_id'] ?? null) || blank($lineData['inventory_location_id'] ?? null)) {
                    continue;
                }

                $item = InventoryItem::findOrFail($lineData['inventory_item_id']);
                $unitCost = (float) ($lineData['unit_cost_snapshot'] ?? 0);
                $qty = (float) ($lineData['quantity_approved'] ?? 0);

                if ($qty <= 0) {
                    continue;
                }

                DeductionRequestLine::create([
                    'deduction_request_id' => $request->id,
                    'inventory_item_id' => $item->id,
                    'inventory_location_id' => $lineData['inventory_location_id'],
                    'quantity_suggested' => $qty,
                    'quantity_approved' => $qty,
                    'unit_cost_snapshot' => $unitCost,
                    'line_total' => round($qty * $unitCost, 2),
                    'raw_description' => $lineData['raw_description'] ?: $item->name,
                    'ai_confidence' => 'manual',
                    'ai_reason' => 'Selected manually while entering sold items for the show.',
                    'ops_overridden' => true,
                ]);
            }

            if ($request->lines()->exists()) {
                $show->update([
                    'status' => 'pending_approval',
                ]);
            }

            return $request->fresh(['lines', 'streamer']);
        });
    }
}
