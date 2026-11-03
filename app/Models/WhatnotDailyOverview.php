<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A channel's day from the Seller API overview report (WhatnotApiOverviewImporter). */
class WhatnotDailyOverview extends Model
{
    protected $guarded = ['id'];

    // No date cast: a cast saves '2026-11-02 00:00:00' on SQLite, and then the
    // (channel, date) lookup in updateOrCreate misses. The day stays 'Y-m-d'.
    protected $casts = [];

    public function channel(): BelongsTo
    {
        return $this->belongsTo(WhatnotChannel::class, 'whatnot_channel_id');
    }
}
