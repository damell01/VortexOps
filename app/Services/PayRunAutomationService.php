<?php

namespace App\Services;

use App\Models\Payout;
use App\Models\Setting;
use App\Models\Show;
use App\Models\WeeklyPayoutBatch;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * One calculation path for scheduled, manual and backfill pay-run setup.
 *
 * This service deliberately operates only on Draft data. Finalized/submitted/
 * paid payroll is historical truth and is never silently recalculated.
 */
class PayRunAutomationService
{
    public function __construct(private readonly PayoutService $payouts)
    {
    }

    /**
     * Ensure a Monday-Sunday Draft batch exists and refresh every eligible show
     * contribution in that week. Safe to call repeatedly.
     *
     * @return array{batch:WeeklyPayoutBatch,created:bool,shows_scanned:int,payouts_attached:int,warnings:array<int,string>}
     */
    public function syncWeek(string|Carbon $weekStart): array
    {
        $start = $weekStart instanceof Carbon
            ? $weekStart->copy()->startOfWeek(Carbon::MONDAY)
            : Carbon::parse($weekStart)->startOfWeek(Carbon::MONDAY);
        $end = $start->copy()->endOfWeek(Carbon::SUNDAY);

        return DB::transaction(function () use ($start, $end) {
            $batch = WeeklyPayoutBatch::whereDate('week_start', $start->toDateString())
                ->whereDate('week_end', $end->toDateString())
                ->orderBy('id')
                ->first();

            $created = false;
            if (! $batch) {
                $batch = WeeklyPayoutBatch::create([
                    'week_start' => $start->toDateString(),
                    'week_end'   => $end->toDateString(),
                    'status'     => 'draft',
                    'created_by' => auth()->id(),
                    'notes'      => 'Created automatically by Pay Run setup.',
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

    /** @return array<int,array<string,mixed>> */
    public function previewRange(string|Carbon $from, string|Carbon $to, ?string $memberType = null): array
    {
        $start = Carbon::parse($from)->startOfWeek(Carbon::MONDAY);
        $end = Carbon::parse($to)->endOfWeek(Carbon::SUNDAY);
        $rows = [];

        for ($cursor = $start->copy(); $cursor->lte($end); $cursor->addWeek()) {
            $weekEnd = $cursor->copy()->endOfWeek(Carbon::SUNDAY);
            $batch = WeeklyPayoutBatch::whereDate('week_start', $cursor->toDateString())
                ->whereDate('week_end', $weekEnd->toDateString())
                ->orderBy('id')
                ->first();

            $existing = $batch?->payouts()
                ->when($memberType, fn ($q) => $q->whereHas('streamer', fn ($m) => $memberType === 'fulfillment'
                    ? $m->where('member_type', 'fulfillment')
                    : $m->where(fn ($x) => $x->where('member_type', 'streamer')->orWhereNull('member_type'))))
                ->sum('calculated_payout') ?? 0;

            $showIds = Show::query()
                ->whereBetween('show_date', [$cursor->toDateString(), $weekEnd->toDateString()])
                ->whereNotIn('status', ['cancelled'])
                ->when($memberType, fn ($q) => $q->whereHas('streamers', fn ($m) => $memberType === 'fulfillment'
                    ? $m->where('member_type', 'fulfillment')
                    : $m->where(fn ($x) => $x->where('member_type', 'streamer')->orWhereNull('member_type'))))
                ->pluck('id');

            $calculated = Payout::query()
                ->whereIn('show_id', $showIds)
                ->when($memberType, fn ($q) => $q->whereHas('streamer', fn ($m) => $memberType === 'fulfillment'
                    ? $m->where('member_type', 'fulfillment')
                    : $m->where(fn ($x) => $x->where('member_type', 'streamer')->orWhereNull('member_type'))))
                ->sum('calculated_payout');

            $rows[] = [
                'week_start' => $cursor->toDateString(),
                'week_end' => $weekEnd->toDateString(),
                'batch_id' => $batch?->id,
                'batch_status' => $batch?->status,
                'shows_found' => $showIds->count(),
                'existing_amount' => round((float) $existing, 2),
                'calculated_amount' => round((float) $calculated, 2),
                'difference' => round((float) $calculated - (float) $existing, 2),
                'result' => ! $batch
                    ? 'MISSING PAY RUN'
                    : (abs((float) $calculated - (float) $existing) < 0.005 ? 'MATCH' : 'DIFFERENCE'),
                'read_only' => $batch && $batch->status !== 'draft',
            ];
        }

        return $rows;
    }
}
