<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShowFinancial extends Model
{
    protected $fillable = [
        'whatnot_show_id',
        'gross_sales',
        'platform_fee_pct',
        'platform_fee_amount',
        'shipping_collected',
        'tips_collected',
        'owner_platform_fee_pct',
        'net_revenue',
        'cogs',
        'gross_profit',
        'notes',
    ];

    protected $casts = [
        'gross_sales'          => 'decimal:2',
        'platform_fee_pct'     => 'decimal:2',
        'platform_fee_amount'  => 'decimal:2',
        'shipping_collected'   => 'decimal:2',
        'tips_collected'       => 'decimal:2',
        'owner_platform_fee_pct' => 'decimal:2',
        'net_revenue'          => 'decimal:2',
        'cogs'                 => 'decimal:2',
        'gross_profit'         => 'decimal:2',
    ];

    public function show(): BelongsTo
    {
        return $this->belongsTo(WhatnotShow::class, 'whatnot_show_id');
    }

    public function recalculate(): void
    {
        $this->platform_fee_amount = round($this->gross_sales * ($this->platform_fee_pct / 100), 2);
        $this->net_revenue         = round($this->gross_sales - $this->platform_fee_amount, 2);
        $this->gross_profit        = round($this->net_revenue - $this->cogs, 2);
        $this->save();
    }
}
