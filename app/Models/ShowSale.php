<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShowSale extends Model
{
    protected $fillable = [
        'whatnot_show_id',
        'inventory_item_id',
        'suggested_inventory_item_id',
        'item_name',
        'sku',
        'quantity',
        'sale_price',
        'unit_cost',
        'buyer_username',
        'buyer_name',
        'order_id',
        'sale_type',
        'sold_at',
        'ai_matched',
        'ai_confidence',
        'raw_data',
    ];

    protected $casts = [
        'quantity'       => 'decimal:2',
        'sale_price'     => 'decimal:2',
        'unit_cost'      => 'decimal:2',
        'ai_confidence'  => 'decimal:2',
        'ai_matched'     => 'boolean',
        'sold_at'        => 'datetime',
        'raw_data'       => 'array',
    ];

    public function show(): BelongsTo
    {
        return $this->belongsTo(WhatnotShow::class, 'whatnot_show_id');
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }

    public function suggestedItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'suggested_inventory_item_id');
    }

    public function lineTotal(): float
    {
        return (float) $this->sale_price * (float) $this->quantity;
    }

    public static function saleTypeLabels(): array
    {
        return [
            'break_slot'  => 'Break Slot',
            'fixed_price' => 'Fixed Price',
            'auction'     => 'Auction',
            'other'       => 'Other',
        ];
    }
}
