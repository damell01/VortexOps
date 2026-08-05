<?php

namespace App\Models;

use App\Models\Concerns\AuditsUpdates;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Show extends Model
{
    use LogsActivity, AuditsUpdates;

    // AuditsUpdates records the real diff for status transitions; LogsActivity's
    // (empty) auto "updated" entry is suppressed. Scoped to status so frequent
    // metric re-imports don't flood the audit log.
    protected static array $doNotRecordEvents = ['updated'];

    /** @return array<int,string> */
    public function auditableFields(): array
    {
        return ['status'];
    }

    protected $fillable = [
        'whatnot_channel_id',
        'channel_attribution_suspect',
        'financials_revised_after_lock',
        'revision_notes',
        'whatnot_show_id',
        'cover_image_url',
        'title',
        'show_date',
        'start_time',
        'end_time',
        'units_sold',
        'gross_revenue',
        'whatnot_net',
        'whatnot_fees',
        'whatnot_payout_amount',
        'last_synced_at',
        'tips',
        'paper_sales_gross',
        'paper_sales_units',
        'paper_sales_notes',
        'sales_reconciled',
        'show_duration',
        'import_source',
        'detail_url',
        'completed_earnings',
        'avg_order_value',
        'giveaway_spend',
        'giveaways_count',
        'buyers_count',
        'first_time_buyers',
        'returning_buyers',
        'shares_count',
        'max_concurrent_viewers',
        'total_views',
        'avg_order_rating',
        'raw_import_payload',
        'ai_streamer_suggestion',
        'status',
        'status_changed_at',
        'notes',
        'created_by',
        'shipping_surcharge_count',
        'shipping_surcharge_total',
    ];

    protected $casts = [
        'show_date'                   => 'date',
        'start_time'                  => 'datetime',
        'end_time'                    => 'datetime',
        'status_changed_at'           => 'datetime',
        'channel_attribution_suspect' => 'boolean',
        'financials_revised_after_lock' => 'boolean',
        'gross_revenue'          => 'decimal:2',
        'whatnot_net'            => 'decimal:2',
        'whatnot_fees'           => 'decimal:2',
        'whatnot_payout_amount'  => 'decimal:2',
        'last_synced_at'         => 'datetime',
        'tips'                   => 'decimal:2',
        'paper_sales_gross'      => 'decimal:2',
        'sales_reconciled'       => 'boolean',
        'raw_import_payload'     => 'array',
        'ai_streamer_suggestion' => 'array',
        'units_sold'             => 'integer',
        'show_duration'          => 'integer',
        'completed_earnings'     => 'decimal:2',
        'avg_order_value'        => 'decimal:2',
        'giveaway_spend'         => 'decimal:2',
        'avg_order_rating'       => 'decimal:2',
        'giveaways_count'        => 'integer',
        'buyers_count'           => 'integer',
        'first_time_buyers'      => 'integer',
        'returning_buyers'       => 'integer',
        'shares_count'           => 'integer',
        'max_concurrent_viewers' => 'integer',
        'total_views'              => 'integer',
        'shipping_surcharge_count' => 'integer',
        'shipping_surcharge_total' => 'decimal:2',
    ];

    /** Per-instance memo — the table calls profitAndLoss() twice per row (state + tooltip). */
    private ?array $profitAndLossCache = null;

    /**
     * Per-show profit & loss — the single source of truth for the P&L Summary
     * card and the Net Margin column. Margin = (Whatnot net + tips) − approved
     * COGS − streamer payouts, where COGS is the sum of the latest deduction
     * request's line totals. Uses loaded relations / withSum aggregates when
     * present (and explicit queries otherwise) so it stays cheap on list views
     * and safe with lazy loading disabled.
     *
     * @return array{gross: float, net: float, tips: float, cogs: float, payouts: float, margin: float, margin_pct: float}
     */
    public function profitAndLoss(): array
    {
        if ($this->profitAndLossCache !== null) {
            return $this->profitAndLossCache;
        }

        $gross = (float) $this->gross_revenue;
        $net   = (float) $this->whatnot_net;
        $tips  = (float) $this->tips;

        // Approved COGS from the latest deduction request's lines.
        $dr = $this->relationLoaded('latestDeductionRequest')
            ? $this->getRelation('latestDeductionRequest')
            : $this->latestDeductionRequest()->with('lines')->first();

        $cogs = 0.0;
        if ($dr) {
            $cogs = (float) ($dr->relationLoaded('lines')
                ? $dr->lines->sum('line_total')
                : $dr->lines()->sum('line_total'));
        }

        $payouts = $this->payouts_sum_calculated_payout !== null
            ? (float) $this->payouts_sum_calculated_payout
            : (float) $this->payouts()->sum('calculated_payout');

        $base   = $net + $tips;
        $margin = round($base - $cogs - $payouts, 2);

        return $this->profitAndLossCache = [
            'gross'      => round($gross, 2),
            'net'        => round($net, 2),
            'tips'       => round($tips, 2),
            'cogs'       => round($cogs, 2),
            'payouts'    => round($payouts, 2),
            'margin'     => $margin,
            'margin_pct' => $base > 0.0 ? round($margin / $base * 100, 1) : 0.0,
        ];
    }

    public function getNetProfitAttribute(): float
    {
        return $this->profitAndLoss()['margin'];
    }

    /**
     * Audience engagement, derived from the analytics fields imported with each
     * show. Conversion is buyers ÷ peak viewers (falling back to total views).
     *
     * @return array{peak_viewers:int, total_views:int, shares:int, buyers:int, first_time:int, returning:int, rating:?float, conversion_pct:?float, has_data:bool}
     */
    public function engagement(): array
    {
        $peak      = (int) ($this->max_concurrent_viewers ?? 0);
        $views     = (int) ($this->total_views ?? 0);
        $buyers    = (int) ($this->buyers_count ?? 0);
        $firstTime = (int) ($this->first_time_buyers ?? 0);
        $returning = (int) ($this->returning_buyers ?? 0);

        $denominator = $peak > 0 ? $peak : $views;

        return [
            'peak_viewers'   => $peak,
            'total_views'    => $views,
            'shares'         => (int) ($this->shares_count ?? 0),
            'buyers'         => $buyers,
            'first_time'     => $firstTime,
            'returning'      => $returning,
            'rating'         => $this->avg_order_rating !== null ? (float) $this->avg_order_rating : null,
            'conversion_pct' => $denominator > 0 ? round($buyers / $denominator * 100, 1) : null,
            'has_data'       => ($peak + $views + $buyers + $firstTime + $returning) > 0,
        ];
    }

    protected static function booted(): void
    {
        // Model::clearBootedModels() (called between tests by Laravel's test
        // TestCase::tearDown()) re-triggers booted() on next use — reset the
        // request-scoped outlier cache here too, or a test's static cache entry
        // would otherwise outlive the RefreshDatabase reset and serve stale data
        // to the next test that happens to reuse the same streamer id.
        static::$priorRevenuesCache = [];

        // Stamp status_changed_at whenever the show enters a new status (and on
        // create), so the pipeline board can show accurate time-in-status.
        static::creating(function (Show $show) {
            $show->status_changed_at ??= now();
        });

        static::updating(function (Show $show) {
            if ($show->isDirty('status')) {
                $show->status_changed_at = now();
            }
        });

        // The AuditsUpdates trait records the populated status-change audit entry.
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logAll()->logOnlyDirty();
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(WhatnotChannel::class, 'whatnot_channel_id');
    }

    /** Limit to the admin's currently active channel (App\Support\ChannelContext), if any. */
    public function scopeInChannelContext(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return \App\Support\ChannelContext::isScoped()
            ? $query->where('whatnot_channel_id', \App\Support\ChannelContext::currentId())
            : $query;
    }

    public function streamers(): BelongsToMany
    {
        return $this->belongsToMany(Streamer::class, 'show_streamer')
            ->withPivot('is_primary')
            ->withTimestamps();
    }

    public function primaryStreamer(): ?Streamer
    {
        return $this->streamers()->wherePivot('is_primary', true)->first();
    }

    /** Fulfillment-role users assigned to work this show — scopes their Fulfillment Center. */
    public function fulfillmentUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'show_fulfillment_user')->withTimestamps();
    }

    public function deductionRequests(): HasMany
    {
        return $this->hasMany(DeductionRequest::class);
    }

    public function latestDeductionRequest(): HasOne
    {
        return $this->hasOne(DeductionRequest::class)->latestOfMany();
    }

    public function ingestionLogs(): HasMany
    {
        return $this->hasMany(ShowIngestionLog::class);
    }

    public function payouts(): HasMany
    {
        return $this->hasMany(Payout::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(WhatnotShowOrder::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function shippingSurcharges(): HasMany
    {
        return $this->hasMany(ShippingSurcharge::class);
    }

    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class);
    }

    public function streamerLogEntry(): HasOne
    {
        return $this->hasOne(StreamerLogEntry::class);
    }

    public function changeLogs(): HasMany
    {
        return $this->hasMany(ShowChangeLog::class)->latest();
    }

    /**
     * Log changes for specific fields before updating the model.
     * Call this before updating the model to track what changed.
     */
    public function trackChanges(array $newValues, string $source = 'manual'): self
    {
        $fieldsToTrack = ['gross_revenue', 'whatnot_net', 'whatnot_fees', 'tips', 'status', 'show_date', 'units_sold'];

        foreach ($newValues as $field => $newValue) {
            if (! in_array($field, $fieldsToTrack)) {
                continue;
            }

            $oldValue = $this->{$field};

            // Skip if value hasn't actually changed
            if ((string) $oldValue === (string) $newValue) {
                continue;
            }

            ShowChangeLog::create([
                'show_id' => $this->id,
                'field_name' => $field,
                'old_value' => is_scalar($oldValue) ? (string) $oldValue : json_encode($oldValue),
                'new_value' => is_scalar($newValue) ? (string) $newValue : json_encode($newValue),
                'changed_by' => auth()->user()?->email ?? 'system',
                'source' => $source,
            ]);
        }

        return $this;
    }

    /**
     * Match the show title against active streamer names and auto-attach high-confidence matches.
     * Stores results in ai_streamer_suggestion. Returns the suggestions array.
     */
    public function detectStreamers(): array
    {
        $title = strtolower($this->title ?? '');
        if (empty($title)) {
            return [];
        }

        $suggestions = [];

        // Word-boundary match, not a bare substring check — "Ty" is a plain
        // str_contains() hit inside "Tyler" (its first two letters), which
        // was wrongly attaching both "Ty" and "Tyler" to any show titled
        // with "Tyler" in it. \b requires Ty to stand alone (surrounded by
        // non-word characters or the string edges), so "Tyler" no longer
        // matches "Ty" while "Ty & Luna" or "Ty's show" still do.
        $matchesWholeWord = fn (string $needle, string $haystack): bool =>
            $needle !== '' && preg_match('/\b' . preg_quote($needle, '/') . '\b/u', $haystack) === 1;

        foreach (Streamer::where('status', 'active')->get(['id', 'name']) as $streamer) {
            $lowerName = strtolower($streamer->name);

            if ($matchesWholeWord($lowerName, $title)) {
                $suggestions[] = [
                    'streamer_id'   => $streamer->id,
                    'streamer_name' => $streamer->name,
                    'confidence'    => 'high',
                    'reason'        => "Name \"{$streamer->name}\" found in show title",
                ];
                continue;
            }

            // Check each word of the name (4+ chars to skip short words like "The", "a")
            foreach (explode(' ', $lowerName) as $part) {
                if (strlen($part) >= 4 && $matchesWholeWord($part, $title)) {
                    $suggestions[] = [
                        'streamer_id'   => $streamer->id,
                        'streamer_name' => $streamer->name,
                        'confidence'    => 'medium',
                        'reason'        => "Name part \"{$part}\" found in show title",
                    ];
                    break;
                }
            }
        }

        if (! empty($suggestions)) {
            $this->update(['ai_streamer_suggestion' => $suggestions]);

            // Auto-attach only when the show has no streamers yet
            if ($this->streamers()->count() === 0) {
                $first = true;
                foreach ($suggestions as $s) {
                    if ($s['confidence'] === 'high') {
                        $this->streamers()->attach($s['streamer_id'], ['is_primary' => $first]);
                        $first = false;
                    }
                }
            }
        }

        return $suggestions;
    }

    /**
     * Return the primary streamer's best inventory location for deduction line defaults.
     * Prefers type=streamer_inventory, falls back to any active location owned by that streamer.
     */
    public function defaultInventoryLocation(): ?InventoryLocation
    {
        $streamer = $this->relationLoaded('streamers')
            ? $this->streamers->first()
            : $this->streamers()->first();

        if (! $streamer) {
            return null;
        }

        return InventoryLocation::where('streamer_id', $streamer->id)
            ->where('status', 'active')
            ->orderByRaw("CASE type WHEN 'streamer_inventory' THEN 0 ELSE 1 END")
            ->first();
    }

    /**
     * The full post-show pipeline, from creation through payout — a single
     * source ops can read to see exactly where a show is stuck, instead of
     * cross-referencing the deduction request and streamer log separately.
     * Never touches an unloaded relation directly (lazy loading is disabled
     * outside production), so each step falls back to an explicit query.
     *
     * @return array<int,array{key:string,label:string,status:string,note:?string}>
     */
    public function pipelineSteps(): array
    {
        $dr = $this->relationLoaded('latestDeductionRequest')
            ? $this->getRelation('latestDeductionRequest')
            : $this->latestDeductionRequest()->first();

        $log = $this->relationLoaded('streamerLogEntry')
            ? $this->getRelation('streamerLogEntry')
            : $this->streamerLogEntry()->first();

        $hasPayouts = $this->relationLoaded('payouts')
            ? $this->payouts->isNotEmpty()
            : $this->payouts()->exists();

        $mappingDone    = in_array($this->status, ['pending_approval', 'reconciled', 'closed']);
        $mappingCurrent = in_array($this->status, ['pending_review', 'mapping']);

        $approvalDone    = in_array($this->status, ['reconciled', 'closed']);
        $approvalCurrent = $this->status === 'pending_approval';

        $streamerDone    = $log && in_array($log->status, ['streamer_reviewed', 'admin_approved']);
        $streamerCurrent = $log && $log->status === 'pending';

        $logApprovedDone    = $log && $log->status === 'admin_approved';
        $logApprovedCurrent = $log && $log->status === 'streamer_reviewed';

        // Fulfillment review is an extra step that only applies to pwe_labels-payout
        // streamers, sitting after the regular admin log approval.
        $primaryStreamer      = $this->relationLoaded('streamers') ? $this->streamers->first() : $this->primaryStreamer();
        $needsFulfillmentStep = $primaryStreamer?->payout_type === 'pwe_labels';
        $fulfillmentDone      = $log && $log->fulfillment_reviewed_at !== null;
        $fulfillmentCurrent   = $log && $log->status === 'admin_approved' && ! $fulfillmentDone;

        $lineCount = fn () => $dr ? $dr->lines()->count() : null;

        $steps = [
            [
                'key'    => 'created',
                'label'  => 'Show Created',
                'status' => 'done',
                'note'   => $this->created_at?->format('M j, Y'),
            ],
            [
                'key'    => 'mapped',
                'label'  => 'Items Mapped',
                'status' => $mappingDone ? 'done' : ($mappingCurrent ? 'current' : 'pending'),
                'note'   => $lineCount() !== null ? "{$lineCount()} line" . ($lineCount() === 1 ? '' : 's') : null,
            ],
            [
                'key'    => 'deduction_approved',
                'label'  => 'Deduction Approved',
                'status' => $approvalDone ? 'done' : ($approvalCurrent ? 'current' : 'pending'),
                'note'   => $dr ? (DeductionRequest::statusLabels()[$dr->status] ?? null) : null,
            ],
            [
                'key'    => 'streamer_reviewed',
                'label'  => 'Streamer Reviewed',
                'status' => $streamerDone ? 'done' : ($streamerCurrent ? 'current' : 'pending'),
                'note'   => $log?->streamer_reviewed_at?->format('M j, Y'),
            ],
            [
                'key'    => 'log_approved',
                'label'  => 'Log Approved',
                'status' => $logApprovedDone ? 'done' : ($logApprovedCurrent ? 'current' : 'pending'),
                'note'   => $log?->reviewed_at?->format('M j, Y'),
            ],
        ];

        if ($needsFulfillmentStep) {
            $steps[] = [
                'key'    => 'fulfillment_reviewed',
                'label'  => 'Fulfillment Reviewed',
                'status' => $fulfillmentDone ? 'done' : ($fulfillmentCurrent ? 'current' : 'pending'),
                'note'   => $log?->fulfillment_reviewed_at?->format('M j, Y'),
            ];
        }

        $steps[] = [
            'key'    => 'payout',
            'label'  => 'Payout Calculated',
            'status' => $hasPayouts ? 'done' : 'pending',
            'note'   => null,
        ];

        // Cancelled shows never proceed further — mark whatever's left as skipped
        // rather than a misleading "pending".
        if ($this->status === 'cancelled') {
            foreach ($steps as &$step) {
                if ($step['status'] === 'pending') {
                    $step['status'] = 'skipped';
                }
            }
        }

        return $steps;
    }

    /**
     * True when this show's gross revenue is a statistical outlier compared
     * to the primary streamer's trailing shows — a quick "does this number
     * look right?" signal ops can check before approving payouts off it.
     * Computed live (not stored) so it always reflects current history.
     * Requires at least 5 prior shows for the same streamer, to avoid false
     * positives on thin history.
     */
    /**
     * Request-scoped cache of each streamer's recent-revenue pool, keyed by
     * streamer id. The Shows table renders many rows for the same streamer per
     * page and previously re-ran this query once per row (twice, since both
     * the column's ->color() and ->description() called this) — this shares
     * one query per streamer per request instead.
     *
     * @var array<int,\Illuminate\Support\Collection<int,float>>
     */
    protected static array $priorRevenuesCache = [];

    public function isRevenueOutlier(): bool
    {
        if ($this->gross_revenue === null) {
            return false;
        }

        $streamer = $this->relationLoaded('streamers') ? $this->streamers->first() : $this->primaryStreamer();
        if (! $streamer) {
            return false;
        }

        $pool = static::$priorRevenuesCache[$streamer->id] ??= static::whereHas('streamers', fn ($q) => $q->where('streamers.id', $streamer->id))
            ->whereNotIn('status', ['cancelled', 'draft'])
            ->whereNotNull('gross_revenue')
            ->orderByDesc('show_date')
            ->limit(20)
            ->pluck('gross_revenue', 'id')
            ->map(fn ($v) => (float) $v);

        $priorRevenues = $pool->except([$this->id])->take(10);

        if ($priorRevenues->count() < 5) {
            return false;
        }

        $mean = $priorRevenues->avg();
        if ($mean <= 0) {
            return false;
        }

        $ratio = (float) $this->gross_revenue / $mean;

        return $ratio > 2.5 || $ratio < 0.35;
    }

    /**
     * How this week's revenue-so-far compares to the same point in the last 4
     * weeks — "are we pacing ahead or behind" rather than a raw dollar figure.
     * Each trailing week is measured through the same relative day (e.g. if
     * today is Wednesday, each prior week is summed Mon–Wed too), so a
     * mid-week comparison is never unfairly stacked against a full week.
     * Channel-aware like the rest of the reporting surface.
     *
     * @return array{this_week_revenue:float, baseline_avg:float, pacing_pct:?float, days_into_week:int}
     */
    public static function weekPacing(): array
    {
        $today        = now();
        $daysIntoWeek = $today->dayOfWeekIso; // 1 (Mon) .. 7 (Sun)
        $weekStart    = $today->copy()->startOfWeek();

        // Upper bound includes a time component: a show_date row dated "today"
        // is stored with a midnight timestamp, which a bare date-string upper
        // bound would exclude via lexical comparison on SQLite.
        $revenueThrough = fn (\Illuminate\Support\Carbon $start, \Illuminate\Support\Carbon $end): float =>
            (float) static::whereBetween('show_date', [$start->toDateString(), $end->copy()->endOfDay()->toDateTimeString()])
                ->whereNotIn('status', ['cancelled'])
                ->inChannelContext()
                ->sum('gross_revenue');

        $thisWeekRevenue = $revenueThrough($weekStart, $today);

        $baselineRevenues = [];
        for ($i = 1; $i <= 4; $i++) {
            $priorStart = $weekStart->copy()->subWeeks($i);
            $priorEnd   = $priorStart->copy()->addDays($daysIntoWeek - 1);
            $baselineRevenues[] = $revenueThrough($priorStart, $priorEnd);
        }

        $baselineAvg = array_sum($baselineRevenues) / count($baselineRevenues);
        $pacingPct   = $baselineAvg > 0 ? round((($thisWeekRevenue - $baselineAvg) / $baselineAvg) * 100, 1) : null;

        return [
            'this_week_revenue' => round($thisWeekRevenue, 2),
            'baseline_avg'      => round($baselineAvg, 2),
            'pacing_pct'        => $pacingPct,
            'days_into_week'    => $daysIntoWeek,
        ];
    }

    /**
     * Same idea as weekPacing() but for the month, plus an actual projection:
     * extrapolating this month's current daily run rate across the rest of the
     * month. Baseline is the trailing 3 months, each measured through the same
     * relative day (capped to that month's own length, so pacing on day 31
     * doesn't break in a 30-day baseline month).
     *
     * @return array{this_month_revenue:float, baseline_avg:float, pacing_pct:?float, days_into_month:int, projected_month_total:?float}
     */
    public static function monthPacing(): array
    {
        $today         = now();
        $daysIntoMonth = $today->day;
        $monthStart    = $today->copy()->startOfMonth();

        // Upper bound includes a time component: a show_date row dated "today"
        // is stored with a midnight timestamp, which a bare date-string upper
        // bound would exclude via lexical comparison on SQLite.
        $revenueThrough = fn (\Illuminate\Support\Carbon $start, \Illuminate\Support\Carbon $end): float =>
            (float) static::whereBetween('show_date', [$start->toDateString(), $end->copy()->endOfDay()->toDateTimeString()])
                ->whereNotIn('status', ['cancelled'])
                ->inChannelContext()
                ->sum('gross_revenue');

        $thisMonthRevenue = $revenueThrough($monthStart, $today);

        $baselineRevenues = [];
        for ($i = 1; $i <= 3; $i++) {
            $priorStart = $monthStart->copy()->subMonths($i);
            $priorEnd   = $priorStart->copy()->addDays(min($daysIntoMonth, $priorStart->daysInMonth) - 1);
            $baselineRevenues[] = $revenueThrough($priorStart, $priorEnd);
        }

        $baselineAvg = array_sum($baselineRevenues) / count($baselineRevenues);
        $pacingPct   = $baselineAvg > 0 ? round((($thisMonthRevenue - $baselineAvg) / $baselineAvg) * 100, 1) : null;

        $projectedMonthTotal = $daysIntoMonth > 0
            ? round(($thisMonthRevenue / $daysIntoMonth) * $today->daysInMonth, 2)
            : null;

        return [
            'this_month_revenue'    => round($thisMonthRevenue, 2),
            'baseline_avg'          => round($baselineAvg, 2),
            'pacing_pct'            => $pacingPct,
            'days_into_month'       => $daysIntoMonth,
            'projected_month_total' => $projectedMonthTotal,
        ];
    }

    public static function statusLabels(): array
    {
        return [
            'draft'            => 'Draft',
            'pending_review'   => 'Pending Review',
            'mapping'          => 'Mapping',
            'pending_approval' => 'Pending Approval',
            'reconciled'       => 'Reconciled',
            'closed'           => 'Closed',
            'cancelled'        => 'Cancelled',
        ];
    }

    public static function importSourceLabels(): array
    {
        return [
            'manual'       => 'Manual',
            'auto_whatnot' => 'Auto (Whatnot)',
        ];
    }
}
