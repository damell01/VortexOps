<?php

namespace App\Models;

use App\Models\Concerns\AuditsUpdates;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Activitylog\Models\Concerns\LogsActivity;

class Payout extends Model
{
    use LogsActivity, AuditsUpdates;

    protected static array $doNotRecordEvents = ['updated'];

    protected static function booted(): void
    {
        static::saving(function (Payout $payout) {
            if ($payout->exists && $payout->getOriginal('status') !== 'draft') {
                $financialFields = [
                    'streamer_id', 'show_id', 'payout_type', 'gross_show_revenue',
                    'product_cost', 'hours_worked', 'shipments_count', 'burden_amount',
                    'net_revenue_basis', 'owner_fee_deducted', 'loan_repayment_deducted',
                    'tips_included', 'pwe_count', 'label_count', 'burden_rate_applied',
                    'calculated_payout', 'compensation_snapshot', 'calculation_version',
                ];

                if (collect($financialFields)->contains(fn (string $field) => $payout->isDirty($field))) {
                    throw new \RuntimeException('Finalized payout calculations are historical and cannot be recalculated.');
                }

                return;
            }

            if (! $payout->streamer_id) {
                return;
            }

            $member = Streamer::find($payout->streamer_id);
            if (! $member) {
                return;
            }

            $resolved = $member->effectiveCompensation();
            $payout->compensation_snapshot = [
                'member_type' => $resolved['member_type'],
                'structure' => $resolved['structure'],
                'defaults' => $resolved['defaults'],
                'overrides' => $resolved['overrides'],
                'effective' => $resolved['effective'],
                'legacy' => $resolved['legacy'],
            ];
            $payout->calculation_version = $resolved['version'];
        });
    }

    /** @return array<int,string> */
    public function auditableFields(): array
    {
        return ['status', 'calculated_payout', 'weekly_payout_batch_id', 'calculation_version'];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'calculated_payout', 'weekly_payout_batch_id', 'calculation_version'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('payout');
    }

    protected $fillable = [
        'show_id',
        'streamer_id',
        'whatnot_channel_id',
        'weekly_payout_batch_id',
        'payout_type',
        'gross_show_revenue',
        'product_cost',
        'hours_worked',
        'shipments_count',
        'burden_amount',
        'net_revenue_basis',
        'owner_fee_deducted',
        'loan_repayment_deducted',
        'tips_included',
        'pwe_count',
        'label_count',
        'burden_rate_applied',
        'routing_bank_label',
        'calculated_payout',
        'calculation_notes',
        'compensation_snapshot',
        'calculation_version',
        'status',
        'shipping_surcharge_deducted',
    ];

    protected $casts = [
        'gross_show_revenue' => 'decimal:2',
        'product_cost' => 'decimal:2',
        'hours_worked' => 'decimal:2',
        'shipments_count' => 'integer',
        'burden_amount' => 'decimal:2',
        'net_revenue_basis' => 'decimal:2',
        'owner_fee_deducted' => 'decimal:2',
        'loan_repayment_deducted' => 'decimal:2',
        'tips_included' => 'decimal:2',
        'burden_rate_applied' => 'decimal:4',
        'calculated_payout' => 'decimal:2',
        'pwe_count' => 'integer',
        'label_count' => 'integer',
        'shipping_surcharge_deducted' => 'decimal:2',
        'compensation_snapshot' => 'array',
    ];

    public function show(): BelongsTo { return $this->belongsTo(Show::class, 'show_id'); }
    public function streamer(): BelongsTo { return $this->belongsTo(Streamer::class); }
    public function batch(): BelongsTo { return $this->belongsTo(WeeklyPayoutBatch::class, 'weekly_payout_batch_id'); }
    public function channel(): BelongsTo { return $this->belongsTo(WhatnotChannel::class, 'whatnot_channel_id'); }

    public function scopeInChannelContext(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return \App\Support\ChannelContext::isScoped()
            ? $query->where('whatnot_channel_id', \App\Support\ChannelContext::currentId())
            : $query;
    }

    public static function statusLabels(): array
    {
        return [
            'draft' => 'Draft',
            'approved' => 'Approved',
            'paid' => 'Paid',
        ];
    }
}
