<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DeductionRequest extends Model
{
    protected $fillable = [
        'show_id',
        'streamer_id',
        'status',
        'ai_mapping_notes',
        'rejection_reason',
        'ops_notes',
        'approved_by',
        'approved_at',
        'processed_by',
        'processed_at',
    ];

    protected $casts = [
        'approved_at'  => 'datetime',
        'processed_at' => 'datetime',
    ];

    public function show(): BelongsTo
    {
        return $this->belongsTo(Show::class);
    }

    public function streamer(): BelongsTo
    {
        return $this->belongsTo(Streamer::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(DeductionRequestLine::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function totalCogs(): float
    {
        return (float) $this->lines()->sum('line_total');
    }

    public static function statusLabels(): array
    {
        return [
            'draft'     => 'Draft',
            'pending'   => 'Pending',
            'approved'  => 'Approved',
            'processed' => 'Processed',
            'rejected'  => 'Rejected',
        ];
    }
}
