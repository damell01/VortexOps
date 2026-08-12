<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WhatnotBuyer extends Model
{
    protected $fillable = [
        'whatnot_user_id',
        'username',
        'display_name',
        'avatar_url',
        'total_orders',
        'lifetime_spend',
        'avg_order_value',
        'first_purchase_date',
        'last_purchase_date',
        'raw_data',
    ];

    protected $casts = [
        'first_purchase_date' => 'date',
        'last_purchase_date'  => 'date',
        'lifetime_spend'      => 'decimal:2',
        'avg_order_value'     => 'decimal:2',
        'total_orders'        => 'integer',
        'raw_data'            => 'array',
    ];

    public function orders(): HasMany
    {
        return $this->hasMany(WhatnotShowOrder::class);
    }

    public function recalculateAggregates(): void
    {
        $agg = $this->orders()
            ->selectRaw('COUNT(*) as cnt, SUM(total_price) as total, MIN(show_date) as first_date, MAX(show_date) as last_date')
            ->first();

        $this->update([
            'total_orders'       => $agg->cnt ?? 0,
            'lifetime_spend'     => $agg->total ?? 0,
            'avg_order_value'    => $agg->cnt > 0 ? round($agg->total / $agg->cnt, 2) : null,
            'first_purchase_date' => $agg->first_date,
            'last_purchase_date'  => $agg->last_date,
        ]);
    }
}
