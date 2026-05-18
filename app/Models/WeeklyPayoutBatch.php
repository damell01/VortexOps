<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WeeklyPayoutBatch extends Model
{
    protected $fillable = [
        'week_start',
        'week_end',
        'status',
        'total_payout',
        'notes',
        'created_by',
        'finalized_by',
        'finalized_at',
    ];

    protected $casts = [
        'week_start'   => 'date',
        'week_end'     => 'date',
        'total_payout' => 'decimal:2',
        'finalized_at' => 'datetime',
    ];

    public function payouts(): HasMany
    {
        return $this->hasMany(Payout::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function finalizedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finalized_by');
    }

    public function recalculateTotal(): void
    {
        $this->total_payout = $this->payouts()->sum('calculated_payout');
        $this->save();
    }

    public static function statusLabels(): array
    {
        return [
            'draft'            => 'Draft',
            'finalized'        => 'Finalized',
            'submitted_to_adp' => 'Submitted to ADP',
            'paid'             => 'Paid',
        ];
    }
}
