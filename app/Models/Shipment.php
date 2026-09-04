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
    }

    /**
     * Seller Hub has changed shipment-table column order several times. The
     * scraper keeps the complete visible row in raw_payload.raw_text, so use
     * the text itself as a safety net instead of trusting fixed cell indexes.
     *
     * This also repairs the obvious "312,026 items" bug where Aug 31, 2026 was
     * accidentally parsed as the quantity.
     */
    public function normalizeScrapedPayload(): void
    {
        $payload = is_array($this->raw_payload) ? $this->raw_payload : [];
        $text = trim((string) ($payload['raw_text'] ?? ''));

        if ($text === '') {
            return;
        }

        $text = preg_replace('/\s+/', ' ', $text) ?: $text;
        $months = 'Jan(?:uary)?|Feb(?:ruary)?|Mar(?:ch)?|Apr(?:il)?|May|Jun(?:e)?|Jul(?:y)?|Aug(?:ust)?|Sep(?:t(?:ember)?)?|Oct(?:ober)?|Nov(?:ember)?|Dec(?:ember)?';
        $datePattern = "(?:{$months})\\s+\\d{1,2},\\s+20\\d{2}";

        // Recipient is everything before the expand/collapse control and date.
        if (blank($this->buyer_username)) {
            if (preg_match('/^(.+?)\s+(?:Expand|Collapse)\s+(' . $datePattern . ')\b/i', $text, $m)) {
                $buyer = trim(preg_replace('/\b(?:New|Expand|Collapse)\b/i', ' ', $m[1]) ?: $m[1]);
                $this->buyer_username = $buyer !== '' ? $buyer : null;
            } elseif (preg_match('/^(.+?)\s+(' . $datePattern . ')\b/i', $text, $m)) {
                $buyer = trim(preg_replace('/\b(?:New|Expand|Collapse)\b/i', ' ', $m[1]) ?: $m[1]);
                $this->buyer_username = $buyer !== '' ? $buyer : null;
            }
        }

        // Capture the actual shipment/order date from the row instead of using
        // the show's date at midnight.
        if (preg_match('/\b(' . $datePattern . ')\b/i', $text, $m)) {
            try {
                $this->created_at_whatnot = Carbon::parse($m[1])->startOfDay();
            } catch (\Throwable) {
                // Keep the existing value if Whatnot changes date formatting.
            }
        }

        // The number directly after the date is the item count. Fixed-index
        // parsing used the date cell itself, producing values like 312,026.
        if (preg_match('/\b' . $datePattern . '\s+(\d{1,5})\b/i', $text, $m)) {
            $qty = (int) $m[1];
            if ($qty > 0 && $qty <= 10000) {
                $this->item_count = $qty;
            }
        }

        // The first money value after the item count is the row's shipment/order
        // value exposed by Seller Hub. Keep the existing DB column for backward
        // compatibility even though the UI label may be refined separately.
        if (preg_match('/\b' . $datePattern . '\s+\d{1,5}\s+\$\s*([\d,]+(?:\.\d{1,2})?)/i', $text, $m)) {
            $this->shipping_cost = (float) str_replace(',', '', $m[1]);
        }

        $lb = null;
        $oz = null;
        if (preg_match('/(\d+(?:\.\d+)?)\s*lb\b/i', $text, $m)) {
            $lb = (float) $m[1];
        }
        if (preg_match('/(\d+(?:\.\d+)?)\s*oz\b/i', $text, $m)) {
            $oz = (float) $m[1];
        }
        if ($lb !== null || $oz !== null) {
            $this->weight_oz = (($lb ?? 0) * 16) + ($oz ?? 0);
        }

        if (preg_match('/(\d+(?:\.\d+)?)\s*[×x]\s*(\d+(?:\.\d+)?)\s*[×x]\s*(\d+(?:\.\d+)?)\s*in\b/i', $text, $m)) {
            $this->dimensions_json = [
                'length_in' => (float) $m[1],
                'width_in' => (float) $m[2],
                'height_in' => (float) $m[3],
            ];
        }

        if (preg_match('/\b(USPS|UPS|FedEx|DHL)\b/i', $text, $m)) {
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

    public function show(): BelongsTo
    {
        return $this->belongsTo(Show::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(WhatnotShowOrder::class, 'whatnot_order_id');
    }
}
