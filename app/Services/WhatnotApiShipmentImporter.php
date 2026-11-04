<?php

namespace App\Services;

use App\Models\Shipment;
use App\Models\WhatnotShowOrder;
use RuntimeException;

/**
 * Bring a Seller API shipment (GET /shipments, Shipment schema) and its items
 * (GET /shipments/{id}/items, ShipmentItem → order_id) into VortexOps:
 *
 *  - each order on it gets the shipment's ID, link, status, carrier, service,
 *    tracking number, weight and box size (the fields the scraper fills);
 *  - a Shipment row per show and tracking number, like the scraper keeps.
 *
 * Status never moves backwards (e.g. delivered → shipped), matching the
 * scraper's rule. Weight is stored in ounces and sizes in inches.
 */
final class WhatnotApiShipmentImporter
{
    /** Whatnot ShipmentStatus → VortexOps shipping status. */
    private const STATUS = [
        'created' => 'pending',
        'label_generated' => 'label_created',
        'dropped_off' => 'shipped',
        'in_transit' => 'in_transit',
        'delivered' => 'delivered',
        'failed' => 'failed',
        'cancelled' => 'cancelled',
        'cancelled_not_shipped' => 'cancelled',
        'cancelled_not_shipped_with_dropoff' => 'cancelled',
        'cancelled_tracking_stuck' => 'failed',
    ];

    /**
     * @param array<string,mixed> $shipment
     * @param list<array<string,mixed>> $items
     * @return array{outcome:string, orders:int}
     */
    public function import(int $channelId, array $shipment, array $items, bool $dryRun = true): array
    {
        if (! $dryRun && ! WhatnotApiOrderApplier::writesAllowed()) {
            throw new RuntimeException('Applying API data is disabled.');
        }
        $shipmentId = is_string($shipment['id'] ?? null) ? $shipment['id'] : null;
        if (! $shipmentId) {
            return ['outcome' => 'invalid', 'orders' => 0];
        }
        $orderIds = array_values(array_unique(array_filter(array_map(
            fn ($item) => is_array($item) && (is_string($item['order_id'] ?? null) || is_int($item['order_id'] ?? null)) ? (string) $item['order_id'] : null,
            $items,
        ))));
        if ($orderIds === []) {
            return ['outcome' => 'no_orders', 'orders' => 0];
        }

        $orders = WhatnotShowOrder::query()
            ->whereIn('whatnot_order_id', $orderIds)
            ->whereHas('show', fn ($q) => $q->where('whatnot_channel_id', $channelId))
            ->get();
        if ($orders->isEmpty()) {
            return ['outcome' => 'orders_not_here', 'orders' => 0];
        }

        $tracking = is_array($shipment['tracking'] ?? null) ? $shipment['tracking'] : [];
        $status = self::STATUS[$shipment['status'] ?? ''] ?? null;
        $weightOz = $this->ounces($shipment['weight'] ?? null);
        $dims = $this->inches($shipment['dimensions'] ?? null);
        $fields = array_filter([
            'whatnot_shipment_id' => $shipmentId,
            'whatnot_shipment_url' => is_string($shipment['url'] ?? null) ? $shipment['url'] : null,
            'tracking_number' => $tracking['tracking_code'] ?? null,
            'shipping_carrier' => $tracking['carrier'] ?? null,
            'shipping_service' => $tracking['service'] ?? null,
            'shipment_weight_oz' => $weightOz,
            'box_length_in' => $dims['length_in'] ?? null,
            'box_width_in' => $dims['width_in'] ?? null,
            'box_height_in' => $dims['height_in'] ?? null,
        ], fn ($v) => $v !== null && $v !== '');

        $rank = ['pending' => 0, 'label_created' => 1, 'packed' => 2, 'shipped' => 3, 'in_transit' => 4, 'delivered' => 5, 'returned' => 6];
        $changed = 0;
        foreach ($orders as $order) {
            $update = array_filter($fields, fn ($v, $k) => (string) $order->{$k} !== (string) $v, ARRAY_FILTER_USE_BOTH);
            if ($status !== null && (in_array($status, ['failed', 'cancelled'], true)
                || $order->shipping_status === null
                || ($rank[$status] ?? 0) > ($rank[$order->shipping_status] ?? -1))) {
                $update['shipping_status'] = $status;
            }
            if ($update === []) continue;
            $changed++;
            if (! $dryRun) {
                $raw = $order->raw_data ?? [];
                $raw['_seller_api_shipment'] = ['id' => $shipmentId, 'status' => $shipment['status'] ?? null, 'received_at' => now()->toIso8601String()];
                $order->forceFill($update + ['shipment_synced_at' => now(), 'raw_data' => $raw])->save();
            }
        }

        // One Shipment row per show and tracking number, as the scraper keeps them.
        if (! $dryRun) {
            foreach ($orders->groupBy('show_id') as $showId => $showOrders) {
                $money = $shipment['seller_paid_shipping_cost'] ?? null;
                $recipient = is_array($shipment['recipient'] ?? null) ? $shipment['recipient'] : [];
                $insurance = is_array($shipment['insurance'] ?? null) ? $shipment['insurance'] : null;
                $existing = Shipment::query()->where('show_id', $showId)
                    ->where('raw_payload->shipment_id', $shipmentId)->first();
                if (! $existing && filled($tracking['tracking_code'] ?? null)) {
                    $existing = Shipment::query()->where('show_id', $showId)
                        ->where('tracking_number', $tracking['tracking_code'])->first();
                }
                // Ignore stale non-terminal status snapshots for delivered or returned packages.
                $incomingStatus = (string) ($shipment['status'] ?? '');
                $currentStatus = (string) ($existing?->status ?? '');
                $terminal = in_array($currentStatus, ['delivered', 'returned'], true);
                $incomingTerminal = in_array($incomingStatus, ['delivered', 'returned'], true);
                $resolvedStatus = $terminal && ! $incomingTerminal ? $currentStatus : $incomingStatus;
                ($existing ?? new Shipment(['show_id' => $showId]))->fill(
                    array_filter([
                        // A link to the local order row (whatnot_show_orders.id), despite the name.
                        'whatnot_order_id' => $showOrders->first()->id,
                        'tracking_number' => $tracking['tracking_code'] ?? null,
                        'buyer_username' => $recipient['username'] ?? null,
                        'item_count' => count($items),
                        'shipping_cost' => is_array($money) && ($money['currency'] ?? null) === 'USD' && is_numeric($money['amount'] ?? null) ? (float) $money['amount'] : null,
                        'weight_oz' => $weightOz,
                        'dimensions_json' => $dims ?: null,
                        'status' => $resolvedStatus !== '' ? $resolvedStatus : null,
                        'carrier' => $tracking['carrier'] ?? null,
                        'insurance_added' => $insurance !== null,
                        'signature_required' => (bool) ($shipment['signature_required'] ?? false),
                        'raw_payload' => ['_source' => 'seller_api', 'shipment_id' => $shipmentId, 'status' => $shipment['status'] ?? null,
                            'required_actions' => $shipment['required_actions'] ?? [], 'documents' => is_array($shipment['documents'] ?? null) ? $shipment['documents'] : [], 'shipment_url' => $shipment['url'] ?? null, 'dropoff_due' => $shipment['dropoff_due'] ?? null,
                            'replacement_shipment_id' => $shipment['replacement_shipment_id'] ?? null],
                    ], fn ($v) => $v !== null),
                )->save();
            }
        }

        return ['outcome' => $changed > 0 ? 'updated' : 'unchanged', 'orders' => $changed];
    }

    private function ounces(mixed $weight): ?float
    {
        if (! is_array($weight) || ! is_numeric($weight['value'] ?? null)) return null;
        $factor = ['ounce' => 1, 'pound' => 16, 'gram' => 0.035274, 'kilogram' => 35.274][$weight['unit'] ?? ''] ?? null;

        return $factor === null ? null : round((float) $weight['value'] * $factor, 2);
    }

    /** @return array{length_in?:float,width_in?:float,height_in?:float} */
    private function inches(mixed $dims): array
    {
        if (! is_array($dims)) return [];
        $factor = ['inch' => 1, 'feet' => 12, 'centimeter' => 0.393701, 'meter' => 39.3701][$dims['unit'] ?? ''] ?? null;
        if ($factor === null) return [];
        $out = [];
        foreach (['length', 'width', 'height'] as $side) {
            if (is_numeric($dims[$side] ?? null)) $out[$side.'_in'] = round((float) $dims[$side] * $factor, 2);
        }

        return $out;
    }
}
