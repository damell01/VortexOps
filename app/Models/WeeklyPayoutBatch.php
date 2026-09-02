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
            if ($batch->exists && ! $batch->isDirty(['week_start', 'week_end'])) {
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
                ->whereDate('week_start', '<=', $end)
                ->whereDate('week_end', '>=', $start)
                ->first(['id', 'week_start', 'week_end', 'status']);

            if ($overlap) {
                throw new \RuntimeException(
                    'This Pay Run overlaps Pay Run #' . $overlap->id
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

    public static function overlapping(string $start, string $end, ?int $exceptId = null): ?self
    {
        return static::query()
            ->when($exceptId, fn (Builder $query) => $query->where((new static())->getKeyName(), '!=', $exceptId))
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
