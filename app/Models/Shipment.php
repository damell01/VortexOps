<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class Shipment extends Model
{
    protected $fillable = [
        'show_id',
        'whatnot_order_id',
        'buyer_username',
        'created_at_whatnot',
        'item_count',
        'shipping_cost',
        'weight_oz',
        'dimensions_json',
        'status',
        'carrier',
        'tracking_number',
        'insurance_added',
        'signature_required',
        'raw_payload',
    ];

    protected $casts = [
        'created_at_whatnot' => 'datetime',
        'shipping_cost'      => 'decimal:2',
        'weight_oz'          => 'decimal:2',
        'dimensions_json'    => 'array',
        'insurance_added'    => 'boolean',
        'signature_required' => 'boolean',
        'raw_payload'        => 'array',
    ];

    protected static function booted(): void
    {
        static::saving(function (Shipment $shipment): void {
            $shipment->normalizeScrapedPayload();
        });

        static::saved(function (Shipment $shipment): void {
            $shipment->reconcileBundledOrders();
        });
    }

    /** Normalize the authoritative fields exposed by Whatnot Seller Hub. */
    public function normalizeScrapedPayload(): void
    {
        $payload = is_array($this->raw_payload) ? $this->raw_payload : [];
        $text = trim((string) ($payload['raw_text'] ?? ''));
        $parserVersion = (int) ($payload['parser_version'] ?? 0);

        if (! empty($payload['buyer'])) {
            $this->buyer_username = (string) $payload['buyer'];
        }

        if (! empty($payload['ordered_at'])) {
            try {
                $this->created_at_whatnot = Carbon::parse($payload['ordered_at'])->startOfDay();
            } catch (\Throwable) {
            }
        }

        if (isset($payload['quantity']) && is_numeric($payload['quantity'])) {
            $qty = (int) $payload['quantity'];
            if ($qty > 0 && $qty <= 10000) {
                $this->item_count = $qty;
            }
        }

        if (isset($payload['weight_oz']) && is_numeric($payload['weight_oz'])) {
            $this->weight_oz = (float) $payload['weight_oz'];
        }

        if (! empty($payload['shipping_carrier'])) {
            $this->carrier = strtoupper((string) $payload['shipping_carrier']);
        }

        if (! empty($payload['shipping_status_scraped'])) {
            $this->status = (string) $payload['shipping_status_scraped'];
        }

        if (isset($payload['box_length_in']) || isset($payload['box_width_in']) || isset($payload['box_height_in'])) {
            $dims = array_filter([
                'length_in' => $payload['box_length_in'] ?? null,
                'width_in' => $payload['box_width_in'] ?? null,
                'height_in' => $payload['box_height_in'] ?? null,
            ], fn ($value) => $value !== null && $value !== '');
            if ($dims !== []) {
                $this->dimensions_json = $dims;
            }
        }

        // Parser v3 separates the shipment's merchandise Value from actual
        // Shipping spend. Older parsers copied Value into shipping_cost, which
        // is why some show cards reported $10k+ of "Shipping".
        if (array_key_exists('shipping_cost_scraped', $payload)) {
            $this->shipping_cost = is_numeric($payload['shipping_cost_scraped'])
                ? round((float) $payload['shipping_cost_scraped'], 2)
                : null;
        } elseif ($parserVersion >= 3) {
            $this->shipping_cost = null;
        } elseif (array_key_exists('total_price', $payload)
            && $this->shipping_cost !== null
            && is_numeric($payload['total_price'])
            && abs((float) $this->shipping_cost - (float) $payload['total_price']) < 0.001) {
            $this->shipping_cost = null;
        }

        if ($text === '') {
            return;
        }

        $text = preg_replace('/\s+/', ' ', $text) ?: $text;
        $months = 'Jan(?:uary)?|Feb(?:ruary)?|Mar(?:ch)?|Apr(?:il)?|May|Jun(?:e)?|Jul(?:y)?|Aug(?:ust)?|Sep(?:t(?:ember)?)?|Oct(?:ober)?|Nov(?:ember)?|Dec(?:ember)?';
        $datePattern = "(?:{$months})\\s+\\d{1,2},\\s+20\\d{2}";

        if (blank($this->buyer_username)) {
            if (preg_match('/^(.+?)\s+(?:Expand|Collapse)\s+(' . $datePattern . ')\b/i', $text, $m)) {
                $buyer = trim(preg_replace('/\b(?:New|Expand|Collapse)\b/i', ' ', $m[1]) ?: $m[1]);
                $this->buyer_username = $buyer !== '' ? $buyer : null;
            } elseif (preg_match('/^(.+?)\s+(' . $datePattern . ')\b/i', $text, $m)) {
                $buyer = trim(preg_replace('/\b(?:New|Expand|Collapse)\b/i', ' ', $m[1]) ?: $m[1]);
                $this->buyer_username = $buyer !== '' ? $buyer : null;
            }
        }

        if (preg_match('/\b(' . $datePattern . ')\b/i', $text, $m)) {
            try {
                $this->created_at_whatnot = Carbon::parse($m[1])->startOfDay();
            } catch (\Throwable) {
            }
        }

        // Always allow the date-anchored quantity to repair legacy 12,026 /
        // 312,026 values caused by parsing the year as part of the item count.
        if (preg_match('/\b' . $datePattern . '\s+(\d{1,5})\b/i', $text, $m)) {
            $qty = (int) $m[1];
            if ($qty > 0 && $qty <= 10000) {
                $this->item_count = $qty;
            }
        }

        if ($this->weight_oz === null) {
            $lb = null;
            $oz = null;
            if (preg_match('/(\d+(?:\.\d+)?)\s*lb\b/i', $text, $m)) $lb = (float) $m[1];
            if (preg_match('/(\d+(?:\.\d+)?)\s*oz\b/i', $text, $m)) $oz = (float) $m[1];
            if ($lb !== null || $oz !== null) $this->weight_oz = (($lb ?? 0) * 16) + ($oz ?? 0);
        }

        if (empty($this->dimensions_json)
            && preg_match('/(\d+(?:\.\d+)?)\s*[×x]\s*(\d+(?:\.\d+)?)\s*[×x]\s*(\d+(?:\.\d+)?)\s*in\b/i', $text, $m)) {
            $this->dimensions_json = [
                'length_in' => (float) $m[1],
                'width_in' => (float) $m[2],
                'height_in' => (float) $m[3],
            ];
        }

        if (blank($this->carrier) && preg_match('/\b(USPS|UPS|FedEx|DHL)\b/i', $text, $m)) {
            $this->carrier = strtoupper($m[1]);
        }

        $this->status = match (true) {
            preg_match('/\bDelivered\b/i', $text) === 1 => 'delivered',
            preg_match('/\bReturned\b/i', $text) === 1 => 'returned',
            preg_match('/\bIn\s+Transit\b/i', $text) === 1 => 'in_transit',
            preg_match('/\bShipping\b|\bShipped\b/i', $text) === 1 => 'shipped',
            preg_match('/\bLabel\s+Created\b/i', $text) === 1 => 'label_created',
            preg_match('/\bReady\s+to\s+Ship\b/i', $text) === 1 => 'ready_to_ship',
            preg_match('/\bPacked\b/i', $text) === 1 => 'packed',
            default => $this->status,
        };

        if (! $this->signature_required && preg_match('/\bSignature\s+Required\b/i', $text)) {
            $this->signature_required = true;
        }
    }

    public function bundledOrderIds(): array
    {
        $payload = is_array($this->raw_payload) ? $this->raw_payload : [];
        $value = $payload['order_ids'] ?? [];

        if (is_string($value)) {
            $value = explode(',', $value);
        }

        return collect(is_array($value) ? $value : [])
            ->push($payload['order_id'] ?? null)
            ->map(fn ($id) => trim((string) $id))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public function bundledItems(): array
    {
        $payload = is_array($this->raw_payload) ? $this->raw_payload : [];
        $value = $payload['bundled_items'] ?? [];

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            return is_array($decoded) ? $decoded : [];
        }

        return is_array($value) ? $value : [];
    }

    /** One Whatnot shipment can contain several sold orders. */
    public function reconcileBundledOrders(): int
    {
        $payload = is_array($this->raw_payload) ? $this->raw_payload : [];
        $orderIds = $this->bundledOrderIds();

        if ($orderIds === [] || ! $this->show_id) {
            return 0;
        }

        $dimensions = is_array($this->dimensions_json) ? $this->dimensions_json : [];
        $shipmentId = $payload['whatnot_shipment_id'] ?? null;
        $shipmentUrl = $payload['shipment_url'] ?? null;
        $service = $payload['shipping_service'] ?? null;

        $orders = WhatnotShowOrder::query()
            ->where('show_id', $this->show_id)
            ->whereIn('whatnot_order_id', $orderIds)
            ->get();

        foreach ($orders as $order) {
            $order->update(array_filter([
                'whatnot_shipment_id' => $shipmentId,
                'whatnot_shipment_url' => $shipmentUrl,
                'tracking_number' => $this->tracking_number,
                'shipping_status' => $this->status,
                'shipment_weight_oz' => $this->weight_oz,
                'box_length_in' => $dimensions['length_in'] ?? null,
                'box_width_in' => $dimensions['width_in'] ?? null,
                'box_height_in' => $dimensions['height_in'] ?? null,
                'shipping_carrier' => $this->carrier,
                'shipping_service' => $service,
                'shipment_synced_at' => now(),
            ], fn ($value) => $value !== null && $value !== ''));
        }

        return $orders->count();
    }

    public function show(): BelongsTo
    {
        return $this->belongsTo(Show::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(WhatnotShowOrder::class, 'whatnot_order_id');
    }
}
