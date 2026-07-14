<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatnotShowOrder extends Model
{
    protected $fillable = [
        'show_id',
        'whatnot_buyer_id',
        'inventory_item_id',
        'inventory_location_id',
        'unit_cost',
        'total_cost',
        'whatnot_order_id',
        'whatnot_show_url',
        'buyer_username',
        'buyer_display_name',
        'lot_number',
        'item_name',
        'item_description',
        'quantity',
        'unit_price',
        'total_price',
        'shipping_amount',
        'tax_amount',
        'fees_amount',
        'net_amount',
        'status',
        'tracking_number',
        'shipping_status',
        'show_date',
        'raw_data',
    ];

    protected $casts = [
        'show_date'       => 'date',
        'raw_data'        => 'array',
        'quantity'        => 'integer',
        'unit_price'      => 'decimal:2',
        'total_price'     => 'decimal:2',
        'unit_cost'       => 'decimal:2',
        'total_cost'      => 'decimal:2',
        'shipping_amount' => 'decimal:2',
        'tax_amount'      => 'decimal:2',
        'fees_amount'     => 'decimal:2',
        'net_amount'      => 'decimal:2',
        'lot_number'      => 'integer',
    ];

    protected static function booted(): void
    {
        // Keep total_cost = quantity × unit_cost whenever a cost is entered, so the
        // streamer only has to set the unit cost inline.
        static::saving(function (WhatnotShowOrder $order) {
            if ($order->unit_cost !== null) {
                $order->total_cost = round((float) $order->unit_cost * (int) ($order->quantity ?: 1), 2);
            }
        });
    }

    public function show(): BelongsTo
    {
        return $this->belongsTo(Show::class);
    }

    /** Limit to the admin's currently active channel (App\Support\ChannelContext), if any. */
    public function scopeInChannelContext(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        if (! \App\Support\ChannelContext::isScoped()) {
            return $query;
        }

        return $query->whereHas('show', fn (\Illuminate\Database\Eloquent\Builder $q) =>
            $q->where('whatnot_channel_id', \App\Support\ChannelContext::currentId())
        );
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(WhatnotBuyer::class, 'whatnot_buyer_id');
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }

    public function inventoryLocation(): BelongsTo
    {
        return $this->belongsTo(\App\Models\InventoryLocation::class);
    }

    public static function statusLabels(): array
    {
        return [
            'completed' => 'Completed',
            'refunded'  => 'Refunded',
            'cancelled' => 'Cancelled',
            'pending'   => 'Pending',
        ];
    }

    public static function shippingStatusLabels(): array
    {
        return [
            'pending' => 'Pending',
            'packed'  => 'Packed',
            'shipped' => 'Shipped',
            'delivered' => 'Delivered',
            'returned'  => 'Returned',
        ];
    }
}
