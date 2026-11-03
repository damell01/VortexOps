<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A channel's day from the Seller API overview report (WhatnotApiOverviewImporter). */
class WhatnotDailyOverview extends Model
{
    protected $guarded = ['id'];

    // Saved as a plain Y-m-d day so the (channel, date) lookup matches on every database.
    protected $casts = ['date' => 'date:Y-m-d'];

    public function channel(): BelongsTo
    {
        return $this->belongsTo(WhatnotChannel::class, 'whatnot_channel_id');
    }
}
