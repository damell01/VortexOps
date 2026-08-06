<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProfitSharePacket extends Model
{
    protected $fillable = [
        'streamer_id',
        'manager_id',
        'year',
        'month',
        'gross_revenue',
        'product_cost',
        'shipping_cost',
        'other_costs',
        'profit_share_pct',
        'profit_share_amount',
        'hourly_rate',
        'hours_worked',
        'hourly_earnings',
        'status',
        'notes',
        'rejection_reason',
        'submitted_at',
        'approved_at',
        'rejected_at',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    public function streamer(): BelongsTo
    {
        return $this->belongsTo(Streamer::class);
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function isSubmitted(): bool
    {
        return $this->status !== 'draft';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    public function submit(): self
    {
        $this->status = 'submitted';
        $this->submitted_at = now();
        $this->save();
        return $this;
    }

    public function approve(): self
    {
        $this->status = 'approved';
        $this->approved_at = now();
        $this->rejected_at = null;
        $this->rejection_reason = null;
        $this->save();
        return $this;
    }

    public function reject(string $reason): self
    {
        $this->status = 'rejected';
        $this->rejected_at = now();
        $this->rejection_reason = $reason;
        $this->approved_at = null;
        $this->save();
        return $this;
    }

    public function calculateTotalCost(): float
    {
        return $this->product_cost + $this->shipping_cost + ($this->other_costs ?? 0);
    }

    public function calculateNetProfit(): float
    {
        return max(0, $this->gross_revenue - $this->calculateTotalCost());
    }

    public function getMonthLabel(): string
    {
        return \Carbon\Carbon::create($this->year, $this->month, 1)->format('F Y');
    }
}
