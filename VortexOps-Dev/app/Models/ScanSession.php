<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ScanSession extends Model
{
    protected $fillable = [
        'user_id',
        'type', // 'inventory', 'receiving', 'shipping', 'lookup'
        'context_id', // pallet_id, shipment_id, order_id, etc
        'context_type', // 'Pallet', 'Shipment', 'Order', null
        'started_at',
        'ended_at',
        'item_count',
        'status', // 'active', 'completed', 'cancelled'
        'metadata', // JSON: {location_id, employee_name, etc}
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'item_count' => 'integer',
        'metadata' => 'array',
    ];

    public function scans(): HasMany
    {
        return $this->hasMany(ScanLog::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function duration()
    {
        if (!$this->ended_at) {
            return null;
        }

        return $this->ended_at->diffInSeconds($this->started_at);
    }
}
