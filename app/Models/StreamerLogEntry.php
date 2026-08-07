<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StreamerLogEntry extends Model
{
    protected $fillable = [
        'show_id',
        'streamer_id',
        'status',
        'hard_copy',
        'hours_streamed',
        'number_of_shipments',
        'pwe_count',
        'label_count',
        'number_of_packages_over_500',
        'pwe_pay',
        'hourly_pay',
        'profit_share_amount',
        'profit_share_paid',
        'tips_paid',
        'total_due',
        'total_paid',
        'business_net_rev',
        'gross_revenue',
        'product_cost',
        'reviewed_by',
        'reviewed_at',
        'streamer_reviewed_at',
        'fulfillment_reviewed_by',
        'fulfillment_reviewed_at',
        'notes',
        'submitted_at',
        'locked_at',
        'edit_window_minutes',
        'approval_requested_at',
        'approval_status',
        'approval_notes',
    ];

    protected $casts = [
        'hard_copy'                    => 'boolean',
        'profit_share_paid'            => 'boolean',
        'hours_streamed'               => 'decimal:2',
        'pwe_pay'                      => 'decimal:2',
        'hourly_pay'                   => 'decimal:2',
        'profit_share_amount'          => 'decimal:2',
        'tips_paid'                    => 'decimal:2',
        'total_due'                    => 'decimal:2',
        'total_paid'                   => 'decimal:2',
        'business_net_rev'             => 'decimal:2',
        'gross_revenue'                => 'decimal:2',
        'product_cost'                 => 'decimal:2',
        'number_of_shipments'          => 'integer',
        'pwe_count'                    => 'integer',
        'label_count'                  => 'integer',
        'number_of_packages_over_500'  => 'integer',
        'edit_window_minutes'          => 'integer',
        'reviewed_at'                  => 'datetime',
        'streamer_reviewed_at'         => 'datetime',
        'submitted_at'                 => 'datetime',
        'locked_at'                    => 'datetime',
        'approval_requested_at'        => 'datetime',
        'fulfillment_reviewed_at'      => 'datetime',
    ];

    public static function statusLabels(): array
    {
        return [
            'pending'           => 'Pending',
            'streamer_reviewed' => 'Streamer Reviewed',
            'admin_approved'    => 'Admin Approved',
        ];
    }

    public function profitShareAmount(): float
    {
        $gross   = (float) ($this->gross_revenue ?? $this->show?->gross_revenue ?? 0);
        $cost    = (float) ($this->product_cost ?? 0);
        $psPct   = (float) ($this->streamer?->payout_percentage ?? 0);
        return round(max(0, $gross - $cost) * ($psPct / 100), 2);
    }

    public function show(): BelongsTo
    {
        return $this->belongsTo(Show::class);
    }

    /** Limit to the admin's currently active channel (App\Support\ChannelContext), if any. */
    public function scopeInChannelContext(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        if (! \App\Support\ChannelContext::isScoped()) {
            return $query;
        }

        return $query->whereHas('show', fn (\Illuminate\Database\Eloquent\Builder $q) =>
            $q->where('whatnot_channel_id', \App\Support\ChannelContext::currentId())
        );
    }

    /**
     * The items sold on this entry's show — matched on show_id so the streamer
     * can enrich them from the log page without opening the show.
     */
    public function showOrders(): HasMany
    {
        return $this->hasMany(WhatnotShowOrder::class, 'show_id', 'show_id');
    }

    public function streamer(): BelongsTo
    {
        return $this->belongsTo(Streamer::class);
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function fulfillmentReviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'fulfillment_reviewed_by');
    }

    /** Fulfillment review only applies to pwe_labels-payout streamers, and only once admin has approved. */
    public function needsFulfillmentReview(): bool
    {
        return $this->status === 'admin_approved'
            && $this->fulfillment_reviewed_at === null
            && ($this->streamer?->payout_type === 'pwe_labels');
    }

    public function isSubmitted(): bool
    {
        return $this->submitted_at !== null;
    }

    public function isLocked(): bool
    {
        return $this->locked_at !== null;
    }

    public function canStreamerEdit(): bool
    {
        if (!$this->isSubmitted()) {
            return true;
        }

        if ($this->isLocked()) {
            return false;
        }

        if ($this->approval_status === 'approved') {
            return false;
        }

        if (!$this->submitted_at) {
            return true;
        }

        $editWindowExpired = $this->submitted_at->addMinutes($this->edit_window_minutes)->isPast();
        return !$editWindowExpired;
    }

    public function getEditWindowExpiresAt(): ?\Illuminate\Support\Carbon
    {
        if (!$this->submitted_at) {
            return null;
        }

        return $this->submitted_at->addMinutes($this->edit_window_minutes);
    }

    public function getMinutesUntilEditWindowCloses(): ?int
    {
        if (!$this->submitted_at) {
            return null;
        }

        $expiresAt = $this->getEditWindowExpiresAt();
        $minutesRemaining = now()->diffInMinutes($expiresAt, false);

        return max(0, $minutesRemaining);
    }

    public function submitReport(): void
    {
        $this->update([
            'submitted_at' => now(),
            'locked_at' => null,
            'edit_window_minutes' => $this->edit_window_minutes ?? 120, // 2 hours default
        ]);
    }

    public function lockReport(): void
    {
        $this->update(['locked_at' => now()]);
    }

    public function unlockReport(): void
    {
        $this->update(['locked_at' => null]);
    }

    public function requestAdminApproval(string $notes = ''): void
    {
        $this->update([
            'approval_requested_at' => now(),
            'approval_status' => 'pending_approval',
            'approval_notes' => $notes,
        ]);
    }

    public function approveByAdmin(): void
    {
        $this->update([
            'approval_status' => 'approved',
            'locked_at' => now(),
        ]);

        if ($this->streamer?->user) {
            \Filament\Notifications\Notification::make()
                ->title('✓ Log Entry Approved')
                ->body("Your log entry for {$this->show?->title} has been approved by admin.")
                ->success()
                ->sendToDatabase($this->streamer->user);
        }
    }

    public function rejectByAdmin(string $notes = ''): void
    {
        $this->update([
            'approval_status' => 'rejected',
            'approval_notes' => $notes,
            'locked_at' => null,
        ]);

        if ($this->streamer?->user) {
            \Filament\Notifications\Notification::make()
                ->title('Changes Requested on Your Log Entry')
                ->body("Your log entry for {$this->show?->title} needs revision.\n\nReason: {$notes}")
                ->warning()
                ->sendToDatabase($this->streamer->user);
        }
    }
}
