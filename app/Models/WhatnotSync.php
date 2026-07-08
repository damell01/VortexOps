<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatnotSync extends Model
{
    protected $fillable = [
        'whatnot_channel_id',
        'type',
        'status',
        'started_at',
        'finished_at',
        'duration_seconds',
        'shows_created',
        'shows_updated',
        'orders_created',
        'orders_updated',
        'buyers_created',
        'buyers_updated',
        'items_created',
        'items_updated',
        'error_count',
        'errors',
        'summary',
    ];

    protected $casts = [
        'started_at'    => 'datetime',
        'finished_at'   => 'datetime',
        'errors'        => 'array',
        'summary'       => 'array',
        'shows_created' => 'integer',
        'shows_updated' => 'integer',
        'orders_created'  => 'integer',
        'orders_updated'  => 'integer',
        'buyers_created'  => 'integer',
        'buyers_updated'  => 'integer',
        'items_created'   => 'integer',
        'items_updated'   => 'integer',
        'error_count'     => 'integer',
        'duration_seconds' => 'integer',
    ];

    public function channel(): BelongsTo
    {
        return $this->belongsTo(WhatnotChannel::class, 'whatnot_channel_id');
    }

    public function markCompleted(array $counters = []): void
    {
        $this->update(array_merge([
            'status'           => 'completed',
            'finished_at'      => now(),
            'duration_seconds' => (int) now()->diffInSeconds($this->started_at),
        ], $counters));
    }

    public function markFailed(\Throwable $e, array $errors = []): void
    {
        $this->update([
            'status'      => 'failed',
            'finished_at' => now(),
            'duration_seconds' => (int) now()->diffInSeconds($this->started_at),
            'error_count' => count($errors) ?: 1,
            'errors'      => count($errors) ? $errors : [['message' => $e->getMessage()]],
        ]);
    }

    public function isRunning(): bool
    {
        return $this->status === 'running';
    }

    public function totalCreated(): int
    {
        return $this->shows_created + $this->orders_created + $this->buyers_created + $this->items_created;
    }

    public function totalUpdated(): int
    {
        return $this->shows_updated + $this->orders_updated + $this->buyers_updated + $this->items_updated;
    }
}
