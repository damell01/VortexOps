<?php

namespace App\Services;

use App\Models\FulfillmentPackage;
use App\Models\Show;
use App\Models\Shipment;
use App\Models\WhatnotShowOrder;
use Illuminate\Support\Collection;

/**
 * One normalized buyer queue, regardless of the upstream provider.
 *
 * Imported Whatnot order rows take priority when present; existing shipment and
 * package records are fallback sources. Order rows may originate from API or scraper. Never merge the sources, as that
 * would double-count buyers and packages during a partial sync.
 *
 * This reads local verified records; the Whatnot API sync job owns network
 * calls, rate limiting and the approval gate for writes.
 */
final class FulfillmentBuyerSource
{
    /** @return Collection<int,array{username:string,order_ids:array,item_count:int,source:string}> */
    public function forShow(Show $show): Collection
    {
        $orderRows = WhatnotShowOrder::query()
            ->where('show_id', $show->id)
            ->whereNotNull('buyer_username')
            ->where('buyer_username', '!=', '')
            ->orderBy('id')
            ->get(['id', 'buyer_username', 'whatnot_order_id', 'quantity']);

        if ($orderRows->isNotEmpty()) {
            return $orderRows->groupBy(fn ($order) => mb_strtolower(trim($order->buyer_username)))
                ->sortKeys()->values()
                ->map(fn ($group) => [
                    'username' => trim($group->first()->buyer_username),
                    'order_ids' => $group->pluck('whatnot_order_id')->filter()->unique()->values()->all(),
                    'item_count' => (int) $group->sum(fn ($order) => max(1, (int) $order->quantity)),
                    'source' => 'orders',
                ]);
        }

        $shipments = Shipment::query()->where('show_id', $show->id)->whereNotNull('buyer_username')
            ->where('buyer_username', '!=', '')->orderBy('id')
            ->get(['id', 'buyer_username', 'item_count']);
        if ($shipments->isNotEmpty()) {
            return $shipments->groupBy(fn ($shipment) => mb_strtolower(trim($shipment->buyer_username)))
                ->sortKeys()->values()
                ->map(fn ($group) => [
                    'username' => trim($group->first()->buyer_username),
                    'order_ids' => [],
                    'item_count' => (int) $group->sum(fn ($shipment) => max(0, (int) $shipment->item_count)),
                    'source' => 'shipments',
                ]);
        }

        $packages = FulfillmentPackage::query()->where('show_id', $show->id)
            ->whereNotNull('buyer_username')->where('buyer_username', '!=', '')
            ->withSum('items', 'quantity')->orderBy('id')
            ->get(['id', 'buyer_username']);

        return $packages->groupBy(fn ($package) => mb_strtolower(trim($package->buyer_username)))
            ->sortKeys()->values()
            ->map(fn ($group) => [
                'username' => trim($group->first()->buyer_username),
                'order_ids' => [],
                'item_count' => (int) $group->sum('items_sum_quantity'),
                'source' => 'packages',
            ]);
    }
}
