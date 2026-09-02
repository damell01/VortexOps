<?php

namespace App\Services;

use App\Models\Payout;
use App\Models\Setting;
use App\Models\Show;
use App\Models\WeeklyPayoutBatch;
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
     * @return array{batch:WeeklyPayoutBatch,created:bool,shows_scanned:int,payouts_attached:int,payouts_detached:int,warnings:array<int,string>}
     */
    public function syncWeek(string|Carbon $weekStart, bool $recalculate = true): array
    {
        $start = $weekStart instanceof Carbon
            ? $weekStart->copy()->startOfWeek(Carbon::MONDAY)
            : Carbon::parse($weekStart)->startOfWeek(Carbon::MONDAY);
        $end = $start->copy()->endOfWeek(Carbon::SUNDAY);

        return DB::transaction(function () use ($start, $end, $recalculate) {
            $batch = WeeklyPayoutBatch::whereDate('week_start', $start->toDateString())
                ->whereDate('week_end', $end->toDateString())
                ->lockForUpdate()
                ->orderBy('id')
                ->first();

            if (! $batch) {
                $overlap = WeeklyPayoutBatch::overlapping($start->toDateString(), $end->toDateString());
                if ($overlap) {
                    throw new \RuntimeException(
                        'Cannot create this Pay Run because it overlaps Pay Run #' . $overlap->id . '.'
                    );
                }

                $batch = WeeklyPayoutBatch::create([
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

            // If a show became blocked after it was already placed in this draft,
            // remove it from the run instead of silently carrying an old payout
            // forward. The payout row remains draft/unbatched and can re-enter
            // after the source show is corrected and recalculated.
            $detached = Payout::query()
                ->where('weekly_payout_batch_id', $batch->id)
                ->where('status', 'draft')
                ->whereNotNull('show_id')
                ->when(
                    $eligibleShowIds->isNotEmpty(),
                    fn ($query) => $query->whereNotIn('show_id', $eligibleShowIds),
                    fn ($query) => $query
                )
                ->update(['weekly_payout_batch_id' => null]);

            $attached = 0;
            if ($eligibleShowIds->isNotEmpty()) {
                $attached = Payout::query()
                    ->whereNull('weekly_payout_batch_id')
                    ->where('status', 'draft')
                    ->whereIn('show_id', $eligibleShowIds)
                    ->update(['weekly_payout_batch_id' => $batch->id]);
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
     * True dry-run validation. The live PayoutService is executed inside a DB
     * transaction and rolled back, so Preview tests the same formula path that
     * production uses without persisting the recalculated payout rows.
     *
     * @return array<int,array<string,mixed>>
     */
    public function previewRange(string|Carbon $from, string|Carbon $to, ?string $memberType = null): array
    {
        $start = Carbon::parse($from)->startOfWeek(Carbon::MONDAY);
        $end = Carbon::parse($to)->endOfWeek(Carbon::SUNDAY);
        $rows = [];

        for ($cursor = $start->copy(); $cursor->lte($end); $cursor->addWeek()) {
            $weekStart = $cursor->copy();
            $weekEnd = $cursor->copy()->endOfWeek(Carbon::SUNDAY);
            $batch = WeeklyPayoutBatch::whereDate('week_start', $weekStart->toDateString())
                ->whereDate('week_end', $weekEnd->toDateString())
                ->orderBy('id')->first();

            $memberFilter = function ($q) use ($memberType) {
                if (! $memberType) {
                    return;
                }
                $q->whereHas('streamer', fn ($m) => $memberType === 'fulfillment'
                    ? $m->where('member_type', 'fulfillment')
                    : $m->where(fn ($x) => $x->where('member_type', 'streamer')->orWhereNull('member_type')));
            };

            $existingQuery = $batch?->payouts();
            if ($existingQuery && $memberType) {
                $memberFilter($existingQuery);
            }
            $existing = $existingQuery?->sum('calculated_payout') ?? 0;

            $shows = Show::query()
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
                        foreach ($this->payouts->calculateForShow($show) as $payout) {
                            if ($memberType === 'fulfillment' && ! $payout->streamer?->isFulfillment()) {
                                continue;
                            }
                            if ($memberType === 'streamer' && $payout->streamer?->isFulfillment()) {
                                continue;
                            }
                            $calculated += (float) $payout->calculated_payout;
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
