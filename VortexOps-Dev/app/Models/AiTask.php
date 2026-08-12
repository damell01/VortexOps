<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AiTask extends Model
{
    protected $fillable = [
        'type',
        'status',
        'taskable_type',
        'taskable_id',
        'triggered_by',
        'input',
        'output',
        'error_message',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'input'        => 'array',
        'output'       => 'array',
        'started_at'   => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function taskable(): MorphTo
    {
        return $this->morphTo();
    }

    public function triggeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by');
    }

    public function markProcessing(): void
    {
        $this->update(['status' => 'processing', 'started_at' => now()]);
    }

    public function markCompleted(array $output): void
    {
        $this->update(['status' => 'completed', 'output' => $output, 'completed_at' => now()]);
    }

    public function markFailed(string $error): void
    {
        $this->update(['status' => 'failed', 'error_message' => $error, 'completed_at' => now()]);
    }

    public function durationSeconds(): ?int
    {
        if (! $this->started_at || ! $this->completed_at) {
            return null;
        }
        return (int) $this->started_at->diffInSeconds($this->completed_at);
    }
}
