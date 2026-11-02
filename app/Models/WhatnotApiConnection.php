<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatnotApiConnection extends Model
{
    protected $fillable = ['whatnot_channel_id', 'access_token', 'enabled', 'sync_enabled', 'status', 'last_checked_at', 'last_success_at', 'last_error'];

    protected $hidden = ['access_token'];

    protected $casts = [
        'access_token' => 'encrypted',
        'enabled' => 'boolean',
        'sync_enabled' => 'boolean',
        'last_checked_at' => 'datetime',
        'last_success_at' => 'datetime',
    ];

    public function channel(): BelongsTo
    {
        return $this->belongsTo(WhatnotChannel::class, 'whatnot_channel_id');
    }
}
