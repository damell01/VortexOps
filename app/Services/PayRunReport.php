<?php

namespace App\Services;

use App\Models\Business;
use App\Models\Payout;
use App\Models\StreamerLogEntry;
use App\Models\WeeklyPayoutBatch;
use App\Models\PayRunReportSnapshot;
use App\Support\BusinessTransfer;
use App\Support\PayRunSchedules;
use Illuminate\Support\Carbon;

/** Read-only reporting: never recalculates pay or changes the transfer formula. */
class PayRunReport
{
    public function run(WeeklyPayoutBatch $run): array
    {
        $run->load(['business', 'payouts.streamer', 'payouts.show']);
        $lines = $run->payouts->map(fn ($p) => [
            'person_id' => $p->streamer_id, 'person' => $p->streamer?->name ?? 'Unassigned',
            'show' => $p->show?->title ?? 'Non-show pay',
            'date' => $p->show?->show_date?->toDateString(),
            'type' => $p->payout_type,
            'hours' => (float) $p->hours_worked,
            'amount' => (float) $p->calculated_payout,
            'status' => $p->status,
        ])->values();

        return [
            'title' => 'Pay Run #'.$run->id,
            'business' => $run->businessLabel(), 'kind' => $run->kindLabel(),
            'period' => $run->week_start->format('M j').' - '.$run->week_end->format('M j, Y'),
            'status' => WeeklyPayoutBatch::statusLabels()[$run->status] ?? $run->status,
            'generated_at' => now()->toIso8601String(),
            'snapshot' => false,
            'total' => round((float) $lines->sum('amount'), 2),
            'people' => $lines->groupBy('person_id')->map(fn ($rows) => [
                'name' => $rows->first()['person'], 'entries' => $rows->count(), 'total' => round((float) $rows->sum('amount'), 2),
            ])->values()->all(),
            'lines' => $lines->all(),
            'transfer' => BusinessTransfer::forRun($run),
            'notes' => $run->notes,
        ];
    }

    /** Called inside the lifecycle transaction, after all payout statuses are updated. */
    public function snapshot(WeeklyPayoutBatch $run, string $stage): void
    {
        if (! in_array($stage, ['finalized', 'paid'], true)) {
            throw new \InvalidArgumentException('Unsupported report snapshot stage.');
        }
        $data = $this->run($run->fresh());
        $data['snapshot'] = true;
        PayRunReportSnapshot::firstOrCreate(
            ['weekly_payout_batch_id' => $run->id, 'stage' => $stage],
            ['payload' => $data, 'created_by' => auth()->id()],
        );
    }

    public function month(Business $business, Carbon $month): array
    {
        $service = app(ProfitLossReport::class);
        // The primary host determines the business, as on the existing P&L report.
        $rows = $service->rows($month->copy()->startOfMonth(), $month->copy()->endOfMonth(), ['business' => $business->id])
            ->where('business_id', $business->id)->values();
        $ids = $rows->pluck('id');
        $productCosts = StreamerLogEntry::whereIn('show_id', $ids)->selectRaw('show_id, SUM(product_cost) as cost')->groupBy('show_id')->pluck('cost', 'show_id');
        $transferPay = Payout::whereIn('show_id', $ids)->whereNotIn('payout_type', PayRunSchedules::TIP_TYPES)->sum('calculated_payout');
        $totals = $service->totals($rows);
        $lines = $rows->map(fn ($r) => [
            'date' => $r['date']?->toDateString(), 'show' => $r['title'], 'person' => $r['streamer'],
            'net' => $r['whatnot_net'], 'tips' => $r['tips'], 'pay' => $r['payouts'],
            'cogs' => $r['cogs'], 'product_cost' => (float) ($productCosts[$r['id']] ?? 0),
            'profit' => $r['profit'], 'incomplete' => $r['incomplete'],
        ])->all();

        return [
            'title' => 'Monthly Business Statement', 'business' => $business->name,
            'period' => $month->format('F Y'), 'generated_at' => now()->toIso8601String(),
            'status' => $totals['incomplete'] > 0 || $month->copy()->endOfMonth()->isFuture() ? 'Provisional' : 'Complete as recorded',
            'totals' => $totals, 'lines' => $lines,
            'transfer' => BusinessTransfer::fromTotals($rows->count(), (float) $productCosts->sum(), (float) $rows->sum('whatnot_net'), (float) $transferPay),
        ];
    }
}
