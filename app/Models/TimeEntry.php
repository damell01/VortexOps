<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One stretch of Hours Worked: clocked in and out, or typed in by hand.
 *
 * It is either linked to a show or carries a reason (prep, cleanup...). An
 * entry that is `ok` is paid as clocked; a `flagged` one waits for an admin
 * and is paid only once `approved`, at the minutes they approved.
 */
class TimeEntry extends Model
{
    public const REASONS = [
        'preparation' => 'Preparation',
        'cleanup' => 'Cleanup',
        'packing' => 'Packing',
        'meeting' => 'Meeting / training',
        'other' => 'Other',
    ];

    public const FLAGS = [
        'edited' => 'Edited by the person',
        'manual' => 'Added by hand, not clocked',
        'missed_clock_out' => 'Missed clock-out (open over 12h)',
        'overlap' => 'Overlaps another entry',
    ];

    /** An entry open this long is treated as a forgotten clock-out. */
    public const MISSED_CLOCK_OUT_HOURS = 12;

    protected $fillable = [
        'user_id',
        'show_id',
        'reason',
        'source',
        'status',
        'flags',
        'approved_minutes',
        'approved_by',
        'approved_at',
        'review_note',
        'edited_at',
        'reminder_sent_at',
        'clocked_in_at',
        'clocked_out_at',
        'notes',
    ];

    protected $casts = [
        'clocked_in_at'  => 'datetime',
        'clocked_out_at' => 'datetime',
        'approved_at' => 'datetime',
        'edited_at' => 'datetime',
        'reminder_sent_at' => 'datetime',
        'flags' => 'array',
        'approved_minutes' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function show(): BelongsTo
    {
        return $this->belongsTo(Show::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function changes(): HasMany
    {
        return $this->hasMany(TimeEntryChange::class)->latest('id');
    }

    public function scopeNeedsReview(Builder $query): Builder
    {
        return $query->where('status', 'flagged');
    }

    public function getDurationMinutesAttribute(): ?int
    {
        if (! $this->clocked_out_at) {
            return null;
        }

        return max(0, (int) $this->clocked_in_at->diffInMinutes($this->clocked_out_at));
    }

    /** Minutes payroll counts: as clocked when fine, as approved once reviewed, none while flagged. */
    public function payableMinutes(): int
    {
        return match ($this->status) {
            'approved' => (int) ($this->approved_minutes ?? $this->duration_minutes ?? 0),
            'ok' => (int) ($this->duration_minutes ?? 0),
            default => 0,
        };
    }

    public function getIsOpenAttribute(): bool
    {
        return $this->clocked_out_at === null;
    }

    public function hasFlag(string $flag): bool
    {
        return in_array($flag, $this->flags ?? [], true);
    }

    /** @return list<string> */
    public function flagLabels(): array
    {
        return array_map(fn ($f) => self::FLAGS[$f] ?? $f, $this->flags ?? []);
    }

    /** What this time was for: the show, or the reason. */
    public function purposeLabel(): string
    {
        if ($this->show_id) {
            return $this->relationLoaded('show') && $this->show
                ? ($this->show->title ?: 'Show #'.$this->show_id)
                : 'Show #'.$this->show_id;
        }

        $label = self::REASONS[$this->reason] ?? null;

        return $label ? $label.($this->notes ? ' — '.$this->notes : '') : ($this->notes ?: 'No show or reason yet');
    }

    public static function formatMinutes(int $minutes): string
    {
        $sign = $minutes < 0 ? '-' : '';
        $minutes = abs($minutes);
        $h = intdiv($minutes, 60);
        $m = $minutes % 60;

        return $sign.($h > 0 ? "{$h}h {$m}m" : "{$m}m");
    }
}
