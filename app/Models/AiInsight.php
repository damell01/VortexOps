<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiInsight extends Model
{
    public const STATUS_OPEN = 'open';
    public const STATUS_REVIEWED = 'reviewed';
    public const STATUS_DISMISSED = 'dismissed';

    protected $fillable = [
        'category',
        'severity',
        'title',
        'summary',
        'details',
        'source_type',
        'source_id',
        'ai_task_id',
        'status',
        'generated_at',
        'expires_at',
    ];

    protected $casts = [
        'details' => 'array',
        'generated_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function aiTask(): BelongsTo
    {
        return $this->belongsTo(AiTask::class);
    }

    public function scopeOpen($query)
    {
        return $query->where('status', self::STATUS_OPEN)
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            });
    }

    public function markReviewed(): void
    {
        $this->update(['status' => self::STATUS_REVIEWED]);
    }

    public function dismiss(): void
    {
        $this->update(['status' => self::STATUS_DISMISSED]);
    }
}
