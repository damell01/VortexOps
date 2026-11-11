<?php

namespace App\Services;

use App\Models\FulfillmentRun;
use Illuminate\Support\Facades\DB;

/**
 * End-of-show check before sign-off: Whatnot's orders and shipments for the
 * show against what was packed on the board. An order "has a package" when
 * a packed package carries its order number, or (for buyers packed as a
 * whole, without per-shipment packages) when its buyer was packed. Anything
 * else is listed by order, so the admin can see exactly what may be missing.
 * Read-only; it never blocks sign-off.
 */
final class FulfillmentReconciliation
{
    private const DEAD = ['cancelled', 'canceled', 'refunded', 'voided', 'failed'];

    /** @return array{orders:int, shipments:int, packed:int, buyers_packed:int, buyers:int, missing_orders:array, missing_shipments:array, ok:bool} */
    public function forRun(FulfillmentRun $run): array
    {
        $orders = DB::table('whatnot_show_orders')->where('show_id', $run->show_id)
            ->where(fn ($q) => $q->whereNull('status')->orWhereNotIn(DB::raw('LOWER(status)'), self::DEAD))
            ->orderBy('buyer_username')->orderBy('id')
            ->get(['id', 'whatnot_order_id', 'buyer_username', 'item_name', 'lot_number']);
        $shipments = DB::table('shipments')->where('show_id', $run->show_id)->get(['id', 'buyer_username', 'tracking_number']);
        $packages = DB::table('fulfillment_run_packages')->where('run_id', $run->id)->get(['buyer_id', 'shipment_id', 'order_ids', 'status']);
        $buyers = DB::table('fulfillment_run_buyers')->where('run_id', $run->id)->get(['id', 'buyer_username', 'status']);

        $packedOrders = [];
        foreach ($packages->where('status', 'packed') as $p) {
            foreach ((array) json_decode((string) $p->order_ids, true) as $id) $packedOrders[(string) $id] = true;
        }
        $buyersWithPackages = $packages->pluck('buyer_id')->unique()->flip();
        $packedBuyers = $buyers->where('status', 'packed')->filter(fn ($b) => ! $buyersWithPackages->has($b->id))
            ->mapWithKeys(fn ($b) => [mb_strtolower(trim($b->buyer_username)) => true]);
        $onBoard = $buyers->mapWithKeys(fn ($b) => [mb_strtolower(trim($b->buyer_username)) => true]);

        $missingOrders = $orders->reject(fn ($o) => isset($packedOrders[(string) $o->whatnot_order_id])
                || isset($packedBuyers[mb_strtolower(trim((string) $o->buyer_username))]))
            ->map(fn ($o) => [
                'order' => $o->whatnot_order_id, 'buyer' => $o->buyer_username, 'item' => $o->item_name, 'lot' => $o->lot_number,
                'why' => $onBoard->has(mb_strtolower(trim((string) $o->buyer_username))) ? 'Not in a packed package' : 'Buyer was not on the board',
            ])->values()->all();

        $packedShipments = $packages->where('status', 'packed')->pluck('shipment_id')->filter()->flip();
        $missingShipments = $packages->whereNotNull('shipment_id')->isEmpty() ? [] : $shipments->reject(fn ($s) => $packedShipments->has($s->id))
            ->map(fn ($s) => ['shipment' => $s->id, 'buyer' => $s->buyer_username, 'tracking' => $s->tracking_number])->values()->all();

        return [
            'orders' => $orders->count(),
            'shipments' => $shipments->count(),
            'packed' => $packages->where('status', 'packed')->count(),
            'buyers' => $buyers->count(),
            'buyers_packed' => $buyers->where('status', 'packed')->count(),
            'missing_orders' => $missingOrders,
            'missing_shipments' => $missingShipments,
            'ok' => $missingOrders === [] && $missingShipments === [],
        ];
    }
}
