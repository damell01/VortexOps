<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhatnotApiSyncAudit extends Model
{
    protected $guarded = ['id'];
    protected $casts = [
        'summary' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];
}
