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
    public function __construct(private readonly PayoutService $payouts)
    {
    }

    /**
     * @return array{batch:WeeklyPayoutBatch,created:bool,shows_scanned:int,payouts_attached:int,warnings:array<int,string>}
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
                ->orderBy('id')->first();

            $created = false;
            if (! $batch) {
                $batch = WeeklyPayoutBatch::create([
                    'week_start' => $start->toDateString(),
                    'week_end' => $end->toDateString(),
                    'status' => 'draft',
                    'created_by' => auth()->id(),
                    'notes' => 'Created automatically by Pay Run setup.',
                ]);
                $created = true;
            }

            if ($batch->status !== 'draft') {
                return [
                    'batch' => $batch,
                    'created' => false,
                    'shows_scanned' => 0,
                    'payouts_attached' => 0,
                    'warnings' => ['Pay Run is ' . $batch->status . ' and was left unchanged.'],
                ];
            }

            $warnings = [];
            $showCount = 0;

            if ($recalculate) {
                $shows = Show::query()
                    ->whereBetween('show_date', [$start->toDateString(), $end->toDateString()])
                    ->whereNotIn('status', ['cancelled'])
                    ->with(['streamers', 'streamerLogEntry'])
                    ->get();

                foreach ($shows as $show) {
                    if ($show->streamers->isEmpty()) {
                        $warnings[] = ($show->title ?: "Show #{$show->id}") . ' has no team member assigned.';
                        continue;
                    }
                    $showCount++;
                    $this->payouts->calculateForShow($show);
                }
            }

            $attached = Payout::query()
                ->whereNull('weekly_payout_batch_id')
                ->where('status', 'draft')
                ->whereHas('show', fn ($query) => $query->whereBetween('show_date', [$start->toDateString(), $end->toDateString()]))
                ->update(['weekly_payout_batch_id' => $batch->id]);

            $batch->recalculateTotal();
            foreach ($this->payouts->signOffProblems($batch) as $problem) {
                $warnings[] = $problem;
            }

            Setting::set('payroll_last_automation_success_at', now()->toISOString());
            Setting::set('payroll_last_automation_error', '');

            return [
                'batch' => $batch->fresh(),
                'created' => $created,
                'shows_scanned' => $showCount,
                'payouts_attached' => $attached,
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
                ->with(['streamers', 'streamerLogEntry'])
                ->get();

            $calculated = 0.0;
            $errors = [];

            DB::beginTransaction();
            try {
                foreach ($shows as $show) {
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
                ? 'FORMULA ERROR'
                : (! $batch ? 'MISSING PAY RUN' : (abs($difference) < 0.005 ? 'MATCH' : 'DIFFERENCE'));

            $rows[] = [
                'week_start' => $weekStart->toDateString(),
                'week_end' => $weekEnd->toDateString(),
                'batch_id' => $batch?->id,
                'batch_status' => $batch?->status,
                'shows_found' => $shows->count(),
                'existing_amount' => round((float) $existing, 2),
                'calculated_amount' => round($calculated, 2),
                'difference' => $difference,
                'result' => $result,
                'warnings' => $errors,
                'read_only' => $batch && $batch->status !== 'draft',
            ];
        }

        return $rows;
    }
}
