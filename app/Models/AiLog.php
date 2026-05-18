<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiLog extends Model
{
    protected $fillable = [
        'model',
        'action_type',
        'prompt',
        'response',
        'context',
        'tokens_estimated',
        'latency_ms',
        'success',
        'error_message',
        'user_id',
    ];

    protected $casts = [
        'context' => 'array',
        'success' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
