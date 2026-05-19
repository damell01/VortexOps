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
        'notes',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logAll()->logOnlyDirty();
    }

    public function shows(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Show::class);
    }

    public static function statusLabels(): array
    {
        return [
            'active' => 'Active',
            'inactive' => 'Inactive',
        ];
    }
}
