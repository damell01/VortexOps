<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One show's fulfillment record: the digital tracking sheet.
 *
 * ready → packing ⇄ paused → completed (packer confirmed done) → approved (final sign-off)
 * (cancelled ends a run without packing it.)
 */
class FulfillmentRun extends Model
{
    public const READY = 'ready';
    public const PACKING = 'packing';
    public const PAUSED = 'paused';
    public const COMPLETED = 'completed';
    public const APPROVED = 'approved';
    public const CANCELLED = 'cancelled';

    /** Statuses in which the run still holds its station. */
    public const ON_STATION = [self::PACKING, self::PAUSED];

    /** Why packing was paused; shown on the board and totalled in reports. */
    public const PAUSE_REASONS = ['break' => 'Break', 'product' => 'Waiting on product', 'issue' => 'Issue', 'other' => 'Other'];

    public static function pauseReasonLabel(?string $key): string
    {
        return self::PAUSE_REASONS[$key ?? ''] ?? 'Other';
    }

    /** Statuses in which the show is still fulfillment work. */
    public const OPEN = [self::READY, self::PACKING, self::PAUSED];

    protected $guarded = ['id'];

    protected $casts = [
        'started_at' => 'datetime', 'finished_at' => 'datetime', 'paused_at' => 'datetime',
        'ready_at' => 'datetime', 'ready_notified_at' => 'datetime', 'acknowledged_at' => 'datetime',
        'approved_at' => 'datetime', 'verified_at' => 'datetime', 'payroll_handoff_at' => 'datetime',
        'discrepancies' => 'array', 'hard_copy_verified' => 'boolean',
        'paused_seconds' => 'integer', 'buyer_count' => 'integer', 'order_count' => 'integer',
        'expected_packages' => 'integer', 'completed_packages' => 'integer',
    ];

    public function show(): BelongsTo { return $this->belongsTo(Show::class); }
    public function station(): BelongsTo { return $this->belongsTo(FulfillmentStation::class, 'station_id'); }
    public function packers(): BelongsToMany { return $this->belongsToMany(User::class, 'fulfillment_run_packers', 'run_id', 'user_id'); }
    public function buyers(): HasMany { return $this->hasMany(FulfillmentRunBuyer::class, 'run_id')->orderBy('id'); }
    public function packages(): HasMany { return $this->hasMany(FulfillmentRunPackage::class, 'run_id'); }
    public function events(): HasMany { return $this->hasMany(FulfillmentEvent::class, 'run_id'); }
    public function flags(): HasMany { return $this->hasMany(FulfillmentFlag::class, 'run_id'); }
    public function currentBuyer(): BelongsTo { return $this->belongsTo(FulfillmentRunBuyer::class, 'current_buyer_id'); }
    public function acknowledger(): BelongsTo { return $this->belongsTo(User::class, 'acknowledged_by'); }
    public function approver(): BelongsTo { return $this->belongsTo(User::class, 'approved_by'); }

    public function isOnStation(): bool { return in_array($this->status, self::ON_STATION, true); }

    /** Seconds actually spent packing: start to finish (or now), less pauses. */
    public function activeSeconds(): int
    {
        if (! $this->started_at) return 0;
        $end = $this->finished_at ?? ($this->paused_at ?? now());
        return max(0, (int) $this->started_at->diffInSeconds($end) - (int) $this->paused_seconds);
    }

    public static function statusLabel(?string $status): string
    {
        return match ($status) {
            self::READY => 'Ready',
            self::PACKING => 'Packing',
            self::PAUSED => 'Paused',
            self::COMPLETED => 'Needs sign-off',
            self::APPROVED => 'Signed off',
            self::CANCELLED => 'Cancelled',
            default => 'Idle',
        };
    }
}
