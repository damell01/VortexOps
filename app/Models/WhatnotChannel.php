<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Activitylog\Models\Concerns\LogsActivity;

class WhatnotChannel extends Model
{
    use LogsActivity;

    protected $fillable = [
        'name',
        'whatnot_username',
        'channel_url',
        'status',
        'include_in_import',
        'notes',
    ];

    protected $casts = [
        'include_in_import' => 'boolean',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logAll()->logOnlyDirty();
    }

    public function shows(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Show::class);
    }

    public function syncs(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(WhatnotSync::class);
    }

    public function latestSync(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(WhatnotSync::class)->latestOfMany('started_at');
    }

    public static function statusLabels(): array
    {
        return [
            'active' => 'Active',
            'inactive' => 'Inactive',
        ];
    }
}
