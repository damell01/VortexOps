<?php

namespace App\Services;

use App\Models\Business;
use App\Models\Payout;
use App\Models\Setting;
use App\Models\Show;
use App\Models\Streamer;
use App\Models\TimeEntry;
use App\Models\WeeklyPayoutBatch;
use App\Support\BusinessContext;
use App\Support\PayRunSchedules;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Shared setup/recalculation path for scheduled, manual and backfill Pay Runs.
 * Finalized/submitted/paid payroll is never silently changed.
 */
class PayRunAutomationService
{
    public function __construct(
        private readonly PayoutService $payouts,
        private readonly ShowWorkflowService $workflow,
        private readonly PayRunReadinessService $readiness,
    ) {
    }

    /**
     * Set up the week for everybody: one run per business once people have
     * been put in businesses, otherwise the single run payroll always had.
     *
     * @return list<array{batch:WeeklyPayoutBatch,created:bool,shows_scanned:int,payouts_attached:int,payouts_detached:int,warnings:array<int,string>}>
     */
    public function syncAllForWeek(string|Carbon $weekStart, bool $recalculate = true, bool $scheduled = false): array
    {
        $businesses = BusinessContext::inUse()
            ? Business::ordered()->whereIn('id', Streamer::query()->whereNotNull('business_id')->distinct()->pluck('business_id'))->pluck('id')->all()
            : [null];

        $results = [];
        if ($scheduled) {
            $types = \App\Models\PayRunType::query()->where('active', true)
                ->where('schedule_enabled', true)->where('auto_create', true)->orderBy('id')->get();
            foreach ($businesses as $businessId) {
                foreach ($types as $type) {
                    [$start] = PayRunSchedules::standardPeriod($type->kind ?? 'wages', $weekStart, $type->scheduleConfig());
                    if ($type->anchor && Carbon::parse($weekStart)->startOfDay()->lt($type->anchor)) {
                        continue;
                    }
                    $results[] = $this->syncWeek($start, $recalculate, $businessId, $type->kind, $type);
                }
            }
        } else {
            foreach ($businesses as $businessId) {
                foreach (PayRunSchedules::activeKinds() as $kind) {
                    $results[] = $this->syncWeek($weekStart, $recalculate, $businessId, $kind);
                }
            }
        }

        return $results;
    }

    /**
     * The week's run for one business, or for everyone when $businessId is
     * null (how payroll worked before businesses).
     *
     * @return array{batch:WeeklyPayoutBatch,created:bool,shows_scanned:int,payouts_attached:int,payouts_detached:int,warnings:array<int,string>}
     */
    public function syncWeek(string|Carbon $weekStart, bool $recalculate = true, ?int $businessId = null, ?string $kind = null, ?\App\Models\PayRunType $type = null): array
    {
        $ofKind = fn ($q) => PayRunSchedules::scopeToKind($q, $kind);

        // The pay period containing that date: an existing run's own dates,
        // otherwise the configured pay week (see PaySchedule).
        [$start, $end] = $type
            ? PayRunSchedules::standardPeriod($kind ?? 'wages', $weekStart, $type->scheduleConfig())
            : PayRunSchedules::periodFor($kind, $weekStart, $businessId);

        return DB::transaction(function () use ($start, $end, $recalculate, $businessId, $kind, $ofKind, $type) {
            $batch = WeeklyPayoutBatch::whereDate('week_start', $start->toDateString())
                ->whereDate('week_end', $end->toDateString())
                ->where(fn ($q) => $businessId === null ? $q->whereNull('business_id') : $q->where('business_id', $businessId))
                ->where(fn ($q) => $kind === null ? $q->whereNull('kind') : $q->where('kind', $kind))
                ->lockForUpdate()
                ->orderBy('id')
                ->first();

            if (! $batch && $businessId !== null && $kind === null) {
                $legacy = WeeklyPayoutBatch::whereDate('week_start', $start->toDateString())
                    ->whereDate('week_end', $end->toDateString())
                    ->whereNull('business_id')
                    ->where('status', 'draft')
                    ->lockForUpdate()
                    ->first();
                if ($legacy) {
                    $batch = collect($this->splitByBusiness($legacy, $businessId)['runs'])
                        ->first(fn (WeeklyPayoutBatch $run) => (int) $run->business_id === $businessId);
                }
            }

            if (! $batch) {
                $overlap = WeeklyPayoutBatch::overlapping($start->toDateString(), $end->toDateString(), null, $businessId, $kind);
                if ($overlap) {
                    throw new \RuntimeException(
                        'Cannot create this Pay Run because it overlaps Pay Run #' . $overlap->id
                        . ($overlap->business_id === null && $businessId !== null ? ' (a run for all businesses — finalize or split it first)' : '') . '.'
                    );
                }

                $batch = WeeklyPayoutBatch::create([
                    'business_id' => $businessId,
                    'kind' => $kind,
                    'pay_run_type_id' => $type?->id,
                    'pay_run_type_name' => $type?->name,
                    'week_start' => $start->toDateString(),
                    'week_end' => $end->toDateString(),
                    'status' => 'draft',
                    'created_by' => auth()->id(),
                    'notes' => 'Created automatically by Pay Run setup.',
                ]);
                $created = true;
            } else {
                $created = false;
            }

            if ($batch->status !== 'draft') {
                return [
                    'batch' => $batch,
                    'created' => false,
                    'shows_scanned' => 0,
                    'payouts_attached' => 0,
                    'payouts_detached' => 0,
                    'warnings' => ['Pay Run is ' . $batch->status . ' and was left unchanged.'],
                ];
            }

            $warnings = [];
            $showCount = 0;
            $eligibleShowIds = collect();

            $shows = Show::query()
                ->where('is_operational', true)
                ->whereBetween('show_date', [$start->toDateString(), $end->toDateString()])
                ->whereNotIn('status', ['cancelled'])
                ->with([
                    'streamers',
                    'streamerLogEntry.streamer',
                    'streamerLogEntry.items.inventoryItem',
                    'fulfillmentUsers',
                    'payouts.batch',
                    'latestDeductionRequest.lines.inventoryItem',
                ])
                ->get();

            foreach ($shows as $show) {
                if ($show->streamers->isEmpty()) {
                    $warnings[] = ($show->title ?: "Show #{$show->id}") . ' has no team member assigned.';
                    continue;
                }

                if ($businessId !== null) {
                    foreach ($show->streamers->whereNull('business_id') as $member) {
                        $warnings[] = "{$member->name} isn't in a business yet, so their pay for "
                            . ($show->title ?: "Show #{$show->id}") . ' is in no Pay Run. Assign them under Payroll → Businesses.';
                    }
                    if (! $show->streamers->contains('business_id', $businessId)) {
                        continue;
                    }
                }

                $state = $this->workflow->stateFor($show);
                $canEnterDraftRun = in_array($state['key'], ['payroll_ready', 'payroll'], true)
                    && ($state['blockers'] ?? []) === [];

                if (! $canEnterDraftRun) {
                    foreach ($state['blockers'] ?? [] as $blocker) {
                        $warnings[] = ($show->title ?: "Show #{$show->id}") . ' — ' . $blocker;
                    }
                    continue;
                }

                $eligibleShowIds->push($show->id);
                $showCount++;

                if ($recalculate) {
                    try {
                        $this->payouts->calculateForShow($show);
                    } catch (\Throwable $e) {
                        $warnings[] = ($show->title ?: "Show #{$show->id}") . ' — payout calculation failed: ' . $e->getMessage();
                        $eligibleShowIds = $eligibleShowIds->reject(fn ($id) => (int) $id === (int) $show->id)->values();
                    }
                }
            }

            $eligibleShowIds = $eligibleShowIds->unique()->values();

            // Anything that is no longer an operational/payroll-ready show is
            // detached from a Draft run. The payout itself remains for history.
            $detached = Payout::query()
                ->where('weekly_payout_batch_id', $batch->id)
                ->where('status', 'draft')
                ->whereNotNull('show_id')
                ->tap($ofKind)
                ->when(
                    $eligibleShowIds->isNotEmpty(),
                    fn ($query) => $query->whereNotIn('show_id', $eligibleShowIds),
                    fn ($query) => $query
                )
                ->update(['weekly_payout_batch_id' => null]);

            // Lines this type of run does not pay go back to wait for their own run.
            $paysHere = Payout::query()->where('weekly_payout_batch_id', $batch->id)->tap($ofKind)->pluck('id');
            $detached += Payout::query()
                ->where('weekly_payout_batch_id', $batch->id)
                ->where('status', 'draft')
                ->whereNotIn('id', $paysHere)
                ->update(['weekly_payout_batch_id' => null]);

            // Someone moved to another business takes their unpaid pay with them.
            if ($businessId !== null) {
                $detached += Payout::query()
                    ->where('weekly_payout_batch_id', $batch->id)
                    ->where('status', 'draft')
                    ->whereDoesntHave('streamer', fn ($s) => $s->where('business_id', $businessId))
                    ->update(['weekly_payout_batch_id' => null]);
            }

            $attached = 0;
            if ($eligibleShowIds->isNotEmpty()) {
                $attached = Payout::query()
                    ->whereNull('weekly_payout_batch_id')
                    ->where('status', 'draft')
                    ->whereIn('show_id', $eligibleShowIds)
                    ->tap($ofKind)
                    ->when($businessId !== null, fn ($q) => $q->whereHas('streamer', fn ($s) => $s->where('business_id', $businessId)))
                    ->update(['weekly_payout_batch_id' => $batch->id]);
            }

            if ($kind === null || $kind === 'wages') {
                $this->syncClockedTime($batch, $start, $end, $businessId);
            }

            $batch->recalculateTotal();

            foreach ($this->readiness->problems($batch) as $problem) {
                $warnings[] = $problem;
            }

            Setting::set('payroll_last_automation_success_at', now()->toISOString());
            Setting::set('payroll_last_automation_error', '');

            return [
                'batch' => $batch->fresh(),
                'created' => $created,
                'shows_scanned' => $showCount,
                'payouts_attached' => $attached,
                'payouts_detached' => $detached,
                'warnings' => array_values(array_unique($warnings)),
            ];
        });
    }

    /**
     * In-house streamers are paid hourly for time that was not on a show —
     * prep, cleanup, packing, meetings. One line per person per run, from the
     * entries that are fine as clocked or an admin approved; flagged time
     * stays out until it is reviewed.
     */
    private function syncClockedTime(WeeklyPayoutBatch $batch, Carbon $start, Carbon $end, ?int $businessId): void
    {
        $keep = [];
        $members = Streamer::query()->whereNotNull('user_id')
            ->when($businessId !== null, fn ($q) => $q->where('business_id', $businessId))
            ->get();

        foreach ($members as $member) {
            $rate = $member->paidHourlyRate();
            if ($rate === null) {
                continue;
            }
            $entries = TimeEntry::where('user_id', $member->user_id)->whereNull('show_id')->whereNotNull('clocked_out_at')
                ->whereBetween('clocked_in_at', [$start->copy()->startOfDay(), $end->copy()->endOfDay()])
                ->get()->filter(fn (TimeEntry $e) => $e->payableMinutes() > 0);
            $minutes = (int) $entries->sum(fn (TimeEntry $e) => $e->payableMinutes());
            if ($minutes <= 0) {
                continue;
            }

            $payout = Payout::firstOrNew([
                'weekly_payout_batch_id' => $batch->id,
                'streamer_id' => $member->id,
                'payout_type' => 'clocked_time',
                'show_id' => null,
            ]);
            if ($payout->exists && $payout->status !== 'draft') {
                $keep[] = $payout->id;
                continue;
            }

            $hours = round($minutes / 60, 2);
            $breakdown = $entries->groupBy(fn (TimeEntry $e) => TimeEntry::REASONS[$e->reason] ?? 'No reason')
                ->map(fn ($g, $label) => $label . ' ' . TimeEntry::formatMinutes((int) $g->sum(fn ($e) => $e->payableMinutes())))
                ->join(' · ');

            $payout->fill([
                'whatnot_channel_id' => null,
                'gross_show_revenue' => 0,
                'hours_worked' => $hours,
                'owner_fee_deducted' => 0,
                'tips_included' => 0,
                'calculated_payout' => round($rate * $hours, 2),
                'calculation_notes' => "Time not on a show: \${$rate}/hr × {$hours}hrs ({$breakdown})",
                'status' => 'draft',
            ])->save();
            $keep[] = $payout->id;
        }

        Payout::where('weekly_payout_batch_id', $batch->id)->where('payout_type', 'clocked_time')
            ->where('status', 'draft')->whereNotIn('id', $keep ?: [0])->delete();
    }

    /**
     * Turn a draft run from before businesses into one run per business.
     *
     * The run keeps its id and dates and becomes the first business's (the
     * preferred one if it has anybody in it); each other business gets a new
     * run for the same dates. Pay for people not in a business yet is taken
     * out and left unbatched, to join a run once they are assigned.
     *
     * @return array{runs:list<WeeklyPayoutBatch>,unassigned:list<string>}
     */
    public function splitByBusiness(WeeklyPayoutBatch $batch, ?int $preferBusinessId = null): array
    {
        return DB::transaction(function () use ($batch, $preferBusinessId) {
            $batch = WeeklyPayoutBatch::lockForUpdate()->findOrFail($batch->id);
            if ($batch->business_id !== null) {
                return ['runs' => [$batch], 'unassigned' => []];
            }
            if ($batch->status !== 'draft') {
                throw new \RuntimeException('Only a draft Pay Run can be split by business.');
            }

            $payouts = $batch->payouts()->with('streamer:id,name,business_id')->get();
            $groups = $payouts->groupBy(fn (Payout $p) => $p->streamer?->business_id ?? 0);

            $unassigned = $groups->pull(0, collect());
            if ($unassigned->isNotEmpty()) {
                Payout::whereKey($unassigned->modelKeys())->update(['weekly_payout_batch_id' => null]);
            }

            $businessIds = $groups->keys()->map(fn ($id) => (int) $id);
            $first = $preferBusinessId !== null && ($businessIds->contains($preferBusinessId) || $businessIds->isEmpty())
                ? $preferBusinessId
                : ($businessIds->first() ?? Business::ordered()->first()?->id);

            if ($first === null) {
                throw new \RuntimeException('Add a business before splitting Pay Runs.');
            }

            $batch->update(['business_id' => $first]);
            $runs = [$batch];

            foreach ($businessIds->reject(fn ($id) => $id === $first) as $businessId) {
                $run = WeeklyPayoutBatch::create([
                    'business_id' => $businessId,
                    'kind' => $batch->kind,
                    'pay_run_type_id' => $batch->pay_run_type_id,
                    'pay_run_type_name' => $batch->pay_run_type_name,
                    'week_start' => $batch->week_start->toDateString(),
                    'week_end' => $batch->week_end->toDateString(),
                    'status' => 'draft',
                    'created_by' => auth()->id() ?? $batch->created_by,
                    'notes' => "Split from Pay Run #{$batch->id}.",
                ]);
                Payout::whereKey($groups[$businessId]->modelKeys())->update(['weekly_payout_batch_id' => $run->id]);
                $run->recalculateTotal();
                $runs[] = $run;
            }

            $batch->recalculateTotal();

            return [
                'runs' => $runs,
                'unassigned' => $unassigned->map(fn (Payout $p) => $p->streamer?->name ?? 'Unknown')->unique()->values()->all(),
            ];
        });
    }

    /**
     * True dry-run validation. The live PayoutService is executed inside a DB
     * transaction and rolled back, so Preview tests the same formula path that
     * production uses without persisting the recalculated payout rows.
     *
     * @return array<int,array<string,mixed>>
     */
    public function previewRange(string|Carbon $from, string|Carbon $to, ?string $memberType = null): array
    {
        $rows = [];

        foreach (\App\Support\PaySchedule::periodsBetween($from, $to) as [$weekStart, $weekEnd]) {
            // Once payroll is split by business a week can have one run per
            // business; the check is against all of them together.
            $runs = WeeklyPayoutBatch::whereDate('week_start', $weekStart->toDateString())
                ->whereDate('week_end', $weekEnd->toDateString())
                ->orderBy('id')->get();
            $batch = $runs->first(fn (WeeklyPayoutBatch $run) => $run->status !== 'draft') ?? $runs->first();

            $memberFilter = function ($q) use ($memberType) {
                if (! $memberType) return;
                $q->whereHas('streamer', fn ($m) => $memberType === 'fulfillment'
                    ? $m->where('member_type', 'fulfillment')
                    : $m->where(fn ($x) => $x->where('member_type', 'streamer')->orWhereNull('member_type')));
            };

            $existingQuery = $runs->isEmpty() ? null : Payout::query()->whereIn('weekly_payout_batch_id', $runs->modelKeys());
            if ($existingQuery && $memberType) $memberFilter($existingQuery);
            $existing = $existingQuery?->where(function ($q) {
                $q->whereNull('show_id')->orWhereHas('show', fn ($show) => $show->where('is_operational', true));
            })->sum('calculated_payout') ?? 0;

            $shows = Show::query()
                ->where('is_operational', true)
                ->whereBetween('show_date', [$weekStart->toDateString(), $weekEnd->toDateString()])
                ->whereNotIn('status', ['cancelled'])
                ->when($memberType, fn ($q) => $q->whereHas('streamers', fn ($m) => $memberType === 'fulfillment'
                    ? $m->where('member_type', 'fulfillment')
                    : $m->where(fn ($x) => $x->where('member_type', 'streamer')->orWhereNull('member_type'))))
                ->with([
                    'streamers',
                    'streamerLogEntry.streamer',
                    'streamerLogEntry.items.inventoryItem',
                    'fulfillmentUsers',
                    'payouts.batch',
                    'latestDeductionRequest.lines.inventoryItem',
                ])
                ->get();

            $calculated = 0.0;
            $errors = [];
            $eligible = 0;

            DB::beginTransaction();
            try {
                foreach ($shows as $show) {
                    $state = $this->workflow->stateFor($show);
                    if (! in_array($state['key'], ['payroll_ready', 'payroll'], true) || ($state['blockers'] ?? []) !== []) {
                        foreach ($state['blockers'] ?? [] as $blocker) {
                            $errors[] = ($show->title ?: "Show #{$show->id}") . ': ' . $blocker;
                        }
                        continue;
                    }

                    $eligible++;
                    try {
                        foreach ($this->payouts->previewForShow($show) as $preview) {
                            if ($memberType === 'fulfillment' && !$preview['streamer']->isFulfillment()) continue;
                            if ($memberType === 'streamer' && $preview['streamer']->isFulfillment()) continue;
                            if ($preview['error']) throw new \RuntimeException($preview['error']);
                            $calculated += (float) $preview['total'];
                        }
                    } catch (\Throwable $e) {
                        $errors[] = ($show->title ?: "Show #{$show->id}") . ': ' . $e->getMessage();
                    }
                }
            } finally {
                DB::rollBack();
            }

            $difference = round($calculated - (float) $existing, 2);
            $result = $errors !== []
                ? 'NEEDS REVIEW'
                : (! $batch ? 'MISSING PAY RUN' : (abs($difference) < 0.005 ? 'MATCH' : 'DIFFERENCE'));

            $rows[] = [
                'week_start' => $weekStart->toDateString(),
                'week_end' => $weekEnd->toDateString(),
                'batch_id' => $batch?->id,
                'batch_status' => $batch?->status,
                'shows_found' => $shows->count(),
                'eligible_shows' => $eligible,
                'existing_amount' => round((float) $existing, 2),
                'calculated_amount' => round($calculated, 2),
                'difference' => $difference,
                'result' => $result,
                'warnings' => array_values(array_unique($errors)),
                'read_only' => $batch && $batch->status !== 'draft',
            ];
        }

        return $rows;
    }
}
