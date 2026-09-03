<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatnotShowOrder extends Model
{
    protected $fillable = [
        'show_id', 'whatnot_buyer_id', 'inventory_item_id', 'inventory_location_id',
        'unit_cost', 'total_cost', 'whatnot_order_id', 'whatnot_show_url',
        'whatnot_order_detail_url', 'whatnot_shipment_id', 'whatnot_shipment_url',
        'ordered_at_whatnot', 'product_category', 'show_category',
        'buyer_username', 'buyer_display_name', 'lot_number', 'item_name', 'item_description',
        'quantity', 'unit_price', 'total_price', 'shipping_amount', 'tax_amount', 'fees_amount',
        'net_amount', 'status', 'tracking_number', 'shipping_status', 'shipment_weight_oz',
        'box_length_in', 'box_width_in', 'box_height_in', 'shipping_carrier', 'shipping_service',
        'shipment_synced_at', 'show_date', 'raw_data',
    ];

    protected $casts = [
        'show_date' => 'date', 'ordered_at_whatnot' => 'datetime', 'raw_data' => 'array',
        'quantity' => 'integer', 'unit_price' => 'decimal:2', 'total_price' => 'decimal:2',
        'unit_cost' => 'decimal:2', 'total_cost' => 'decimal:2', 'shipping_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2', 'fees_amount' => 'decimal:2', 'net_amount' => 'decimal:2',
        'lot_number' => 'integer', 'shipment_weight_oz' => 'decimal:2', 'box_length_in' => 'decimal:2',
        'box_width_in' => 'decimal:2', 'box_height_in' => 'decimal:2', 'shipment_synced_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (WhatnotShowOrder $order) {
            if ($order->unit_cost !== null) {
                $order->total_cost = round((float) $order->unit_cost * (int) ($order->quantity ?: 1), 2);
            }

            // The orders scraper can enrich a row from Whatnot's order-detail
            // sidebar. Promote those values out of raw_data so they are queryable
            // and usable by the UI/payroll without parsing JSON later.
            $raw = is_array($order->raw_data) ? $order->raw_data : [];
            $map = [
                'order_detail_url' => 'whatnot_order_detail_url',
                'shipment_id' => 'whatnot_shipment_id',
                'shipment_url' => 'whatnot_shipment_url',
                'ordered_at' => 'ordered_at_whatnot',
                'order_date' => 'ordered_at_whatnot',
                'product_category' => 'product_category',
                'show_category' => 'show_category',
                'shipping_amount' => 'shipping_amount',
                'shipping' => 'shipping_amount',
                'tax_amount' => 'tax_amount',
                'tax' => 'tax_amount',
                'fees_amount' => 'fees_amount',
                'fees' => 'fees_amount',
                'net_earnings' => 'net_amount',
                'net_amount' => 'net_amount',
            ];

            foreach ($map as $source => $target) {
                if (($order->{$target} === null || $order->{$target} === '') && array_key_exists($source, $raw) && $raw[$source] !== null && $raw[$source] !== '') {
                    $order->{$target} = $raw[$source];
                }
            }
        });
    }

    public function show(): BelongsTo { return $this->belongsTo(Show::class); }

    public function scopeInChannelContext(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        if (! \App\Support\ChannelContext::isScoped()) return $query;
        return $query->whereHas('show', fn (\Illuminate\Database\Eloquent\Builder $q) => $q->where('whatnot_channel_id', \App\Support\ChannelContext::currentId()));
    }

    public function buyer(): BelongsTo { return $this->belongsTo(WhatnotBuyer::class, 'whatnot_buyer_id'); }
    public function inventoryItem(): BelongsTo { return $this->belongsTo(InventoryItem::class); }
    public function inventoryLocation(): BelongsTo { return $this->belongsTo(\App\Models\InventoryLocation::class); }

    public static function statusLabels(): array
    {
        return ['completed' => 'Completed', 'refunded' => 'Refunded', 'cancelled' => 'Cancelled', 'pending' => 'Pending'];
    }

    public static function shippingStatusLabels(): array
    {
        return ['pending' => 'Pending', 'label_created' => 'Label Created', 'packed' => 'Packed', 'shipped' => 'Shipped', 'delivered' => 'Delivered', 'returned' => 'Returned'];
    }
}
