<?php

namespace App\Models;

use App\Models\Concerns\AuditsUpdates;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Activitylog\Models\Concerns\LogsActivity;

class WeeklyPayoutBatch extends Model
{
    use LogsActivity, AuditsUpdates;

    protected static array $doNotRecordEvents = ['updated'];

    protected static function booted(): void
    {
        static::saving(function (WeeklyPayoutBatch $batch): void {
            // Existing legacy data may already contain an overlap. Do not make
            // unrelated updates (totals/status/notes) impossible; enforce the
            // guard when a Pay Run is created or its date range is changed.
            if ($batch->exists && ! $batch->isDirty(['week_start', 'week_end', 'business_id', 'kind'])) {
                return;
            }

            if (! $batch->week_start || ! $batch->week_end) {
                return;
            }

            $start = $batch->week_start instanceof CarbonInterface
                ? $batch->week_start->toDateString()
                : (string) $batch->week_start;
            $end = $batch->week_end instanceof CarbonInterface
                ? $batch->week_end->toDateString()
                : (string) $batch->week_end;

            if ($end < $start) {
                throw new \RuntimeException('Pay Run week end cannot be before week start.');
            }

            $overlap = static::query()
                ->when($batch->exists, fn (Builder $query) => $query->where($batch->getKeyName(), '!=', $batch->getKey()))
                ->sharingPeopleWith($batch->business_id)
                ->sharingKindWith($batch->kind)
                ->whereDate('week_start', '<=', $end)
                ->whereDate('week_end', '>=', $start)
                ->first(['id', 'business_id', 'kind', 'week_start', 'week_end', 'status']);

            if ($overlap) {
                throw new \RuntimeException(
                    'This Pay Run overlaps ' . ($overlap->kind !== $batch->kind ? \App\Support\PayRunSchedules::label($overlap->kind) . ' ' : '') . 'Pay Run #' . $overlap->id
                    . ' (' . $overlap->week_start->format('M j') . '–' . $overlap->week_end->format('M j, Y')
                    . ', ' . (static::statusLabels()[$overlap->status] ?? $overlap->status) . ').'
                );
            }
        });
    }

    /** @return array<int,string> */
    public function auditableFields(): array
    {
        return ['status', 'total_payout', 'finalized_by', 'finalized_at'];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'total_payout', 'finalized_by', 'finalized_at'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('pay_run');
    }

    protected $fillable = [
        'business_id',
        'kind',
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

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * Runs that could pay the same people as a run for $businessId: that
     * business's own runs, and runs from before businesses (null), which pay
     * everybody. A run for no business clashes with every run.
     */
    public function scopeSharingPeopleWith(Builder $query, ?int $businessId): Builder
    {
        return $businessId === null
            ? $query
            : $query->where(fn (Builder $q) => $q->where('business_id', $businessId)->orWhereNull('business_id'));
    }

    /**
     * Runs that could pay the same lines as a run of $kind: runs of that
     * type and "All pay" runs (null), which pay everything. An "All pay" run
     * clashes with every run.
     */
    public function scopeSharingKindWith(Builder $query, ?string $kind): Builder
    {
        return $kind === null
            ? $query
            : $query->where(fn (Builder $q) => $q->where('kind', $kind)->orWhereNull('kind'));
    }

    public function kindLabel(): string
    {
        return \App\Support\PayRunSchedules::label($this->kind);
    }

    /** Runs for the business payroll is being run for, when one is chosen. */
    public function scopeInBusinessContext(Builder $query): Builder
    {
        return \App\Support\BusinessContext::isScoped()
            ? $query->where('business_id', \App\Support\BusinessContext::currentId())
            : $query;
    }

    /** "Estill Distribution", or "All businesses" for a run from before the split. */
    public function businessLabel(): string
    {
        return $this->business_id ? ($this->loadMissing('business')->business?->name ?? 'All businesses') : 'All businesses';
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

    public static function overlapping(string $start, string $end, ?int $exceptId = null, ?int $businessId = null, ?string $kind = null): ?self
    {
        return static::query()
            ->when($exceptId, fn (Builder $query) => $query->where((new static())->getKeyName(), '!=', $exceptId))
            ->sharingPeopleWith($businessId)
            ->sharingKindWith($kind)
            ->whereDate('week_start', '<=', $end)
            ->whereDate('week_end', '>=', $start)
            ->orderBy('week_start')
            ->first();
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
