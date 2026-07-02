<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatnotShowOrder extends Model
{
    protected $fillable = [
        'show_id',
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
        'status',
        'show_date',
        'raw_data',
    ];

    protected $casts = [
        'show_date' => 'date',
        'raw_data'  => 'array',
        'quantity'  => 'integer',
        'unit_price'  => 'decimal:2',
        'total_price' => 'decimal:2',
        'lot_number'  => 'integer',
    ];

    public function show(): BelongsTo
    {
        return $this->belongsTo(Show::class);
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
}
