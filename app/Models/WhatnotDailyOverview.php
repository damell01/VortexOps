<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A channel's day from the Seller API overview report (WhatnotApiOverviewImporter). */
class WhatnotDailyOverview extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['date' => 'date'];

    public function channel(): BelongsTo
    {
        return $this->belongsTo(WhatnotChannel::class, 'whatnot_channel_id');
    }
}
