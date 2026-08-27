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
        'logo_path',
        'display_title',
    ];

    protected $casts = [
        'include_in_import' => 'boolean',
    ];

    /**
     * The channels a scrape should keep up to date.
     *
     * One Whatnot login's Seller Hub lists every show on the account, so a
     * single scrape already carries the data for all of them — restricting
     * enrichment to one channel was arbitrary, and it left every other channel's
     * shows permanently unfetched with nothing saying why.
     *
     * Falls back the way the rest of the importer does, so a database with no
     * flags set still syncs rather than silently doing nothing.
     *
     * @return array<int, int>
     */
    public static function importedIds(): array
    {
        $active = static::query()->where('status', 'active');

        $ids = (clone $active)->where('include_in_import', true)->pluck('id')->all();

        if ($ids !== []) {
            return $ids;
        }

        return $active->pluck('id')->all() ?: static::query()->pluck('id')->all();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logAll()->logOnlyDirty();
    }

    public function shows(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Show::class);
    }

    /** Streamers primarily attributed to this channel. */
    public function streamers(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Streamer::class);
    }

    /** Inventory locations grouped under this channel. */
    public function inventoryLocations(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(InventoryLocation::class);
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
