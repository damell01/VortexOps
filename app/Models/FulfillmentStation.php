<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FulfillmentStation extends Model
{
    protected $fillable = ['name', 'camera_label', 'active', 'sort'];

    protected $casts = ['active' => 'boolean', 'sort' => 'integer'];

    public function runs(): HasMany
    {
        return $this->hasMany(FulfillmentRun::class, 'station_id');
    }

    /** The run packing or paused at this station right now, if any. */
    public function activeRun(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(FulfillmentRun::class, 'station_occupied_id');
    }
}
