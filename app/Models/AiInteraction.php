<?php

namespace App\Models;

use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;

/**
 * One logged AI call. Immutable telemetry — only created_at is tracked — and
 * prunable so the table stays small (kept 30 days; schedule `model:prune`).
 */
class AiInteraction extends Model
{
    use MassPrunable;

    public const UPDATED_AT = null;

    protected $fillable = [
        'provider',
        'task',
        'model',
        'latency_ms',
        'success',
        'error',
        'created_at',
    ];

    protected $casts = [
        'latency_ms' => 'integer',
        'success'    => 'boolean',
        'created_at' => 'datetime',
    ];

    /** Rows older than 30 days are pruned by `php artisan model:prune`. */
    public function prunable(): \Illuminate\Database\Eloquent\Builder
    {
        return static::where('created_at', '<', now()->subDays(30));
    }
}
