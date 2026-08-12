<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScannerReceivingSession extends Model
{
    protected $fillable = [
        'pallet_id',
        'user_id',
        'mode',
        'items_scanned',
        'items_added',
        'started_at',
        'last_activity_at',
        'ended_at',
    ];

    protected $casts = [
        'items_scanned' => 'integer',
        'items_added' => 'integer',
        'started_at' => 'datetime',
        'last_activity_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    public function pallet(): BelongsTo
    {
        return $this->belongsTo(Pallet::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isActive(): bool
    {
        return $this->ended_at === null;
    }

    public function end(): void
    {
        $this->update(['ended_at' => now()]);
    }
}
