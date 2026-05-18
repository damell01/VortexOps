<?php

namespace App\Services;

use App\Models\DeductionRequest;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\ShowFinancial;
use App\Models\ShowSale;
use App\Models\WhatnotShow;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ShowService
{
    public function createShow(array $data): WhatnotShow
    {
        return DB::transaction(function () use ($data) {
            $streamers = $data['streamers'] ?? [];
            unset($data['streamers']);

            $data['created_by'] = Auth::id();
            $show = WhatnotShow::create($data);

            if (!empty($streamers)) {
                $show->streamers()->sync($streamers);
            }

            ShowFinancial::create(['whatnot_show_id' => $show->id]);

            return $show;
        });
    }

    public function generateDeductionRequests(WhatnotShow $show): int
    {
        $created = 0;

        foreach ($show->sales as $sale) {
            if (!$sale->inventory_item_id) {
                continue;
            }

            // Find the streamer's inventory location for this show
            $location = $this->resolveLocation($show, $sale->inventoryItem);
            if (!$location) {
                continue;
            }

            // Avoid duplicate requests for the same sale
            $exists = DeductionRequest::where('show_sale_id', $sale->id)->exists();
            if ($exists) {
                continue;
            }

            DeductionRequest::create([
                'whatnot_show_id'       => $show->id,
                'show_sale_id'          => $sale->id,
                'inventory_item_id'     => $sale->inventory_item_id,
                'inventory_location_id' => $location->id,
                'quantity'              => $sale->quantity,
                'unit_cost'             => $sale->unit_cost,
                'status'                => 'pending',
            ]);

            $created++;
        }

        if ($created > 0 && $show->status === 'draft') {
            $show->update(['status' => 'pending_reconciliation']);
        }

        return $created;
    }

    private function resolveLocation(WhatnotShow $show, InventoryItem $item): ?InventoryLocation
    {
        // Try streamer-assigned locations first (primary streamer wins)
        foreach ($show->streamers()->orderByPivot('is_primary', 'desc')->get() as $streamer) {
            $location = $streamer->inventoryLocations()
                ->where('status', 'active')
                ->whereHas('stock', fn ($q) => $q->where('inventory_item_id', $item->id)->where('quantity', '>', 0))
                ->first();

            if ($location) {
                return $location;
            }
        }

        // Fall back to any active non-streamer location that has stock
        return InventoryLocation::whereNull('streamer_id')
            ->where('status', 'active')
            ->whereHas('stock', fn ($q) => $q->where('inventory_item_id', $item->id)->where('quantity', '>', 0))
            ->first();
    }

    public function updateFinancials(WhatnotShow $show, array $data): ShowFinancial
    {
        $financial = $show->financial ?? ShowFinancial::create(['whatnot_show_id' => $show->id]);
        $financial->fill($data);
        $financial->recalculate();
        return $financial;
    }

    public function importFromArray(array $payload): WhatnotShow
    {
        return DB::transaction(function () use ($payload) {
            $show = $this->createShow([
                'whatnot_channel_id' => $payload['channel_id'] ?? null,
                'title'              => $payload['title'] ?? null,
                'show_date'          => $payload['show_date'] ?? now()->toDateString(),
                'started_at'         => $payload['started_at'] ?? null,
                'ended_at'           => $payload['ended_at'] ?? null,
                'source'             => $payload['source'] ?? 'scraper',
                'raw_data'           => $payload['raw_data'] ?? null,
                'streamers'          => $payload['streamer_ids'] ?? [],
            ]);

            foreach ($payload['sales'] ?? [] as $line) {
                ShowSale::create([
                    'whatnot_show_id' => $show->id,
                    'item_name'       => $line['item_name'] ?? 'Unknown',
                    'sku'             => $line['sku'] ?? null,
                    'quantity'        => $line['quantity'] ?? 1,
                    'sale_price'      => $line['sale_price'] ?? 0,
                    'buyer_username'  => $line['buyer_username'] ?? null,
                    'buyer_name'      => $line['buyer_name'] ?? null,
                    'order_id'        => $line['order_id'] ?? null,
                    'sale_type'       => $line['sale_type'] ?? 'break_slot',
                    'sold_at'         => $line['sold_at'] ?? null,
                    'raw_data'        => $line,
                ]);
            }

            if (!empty($payload['financials'])) {
                $this->updateFinancials($show, $payload['financials']);
            }

            return $show;
        });
    }
}
