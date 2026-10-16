<?php

namespace App\Services;

use App\Models\Business;
use App\Models\Payout;
use App\Models\WeeklyPayoutBatch;
use App\Support\BusinessTransfer;
use App\Support\PayRunSchedules;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * A business's month on one statement, ready to send them: totals, the same
 * figures week by week, the pay runs that covered it, and every show. Show
 * figures come from ProfitLossReport (the Profit & Loss page's numbers); the
 * transfer uses the same formula as a pay run (BusinessTransfer).
 */
class BusinessStatement
{
    public function __construct(private ProfitLossReport $report) {}

    /** @return array<string, mixed> */
    public function build(Business $business, \Carbon\CarbonInterface $month): array
    {
        $month = Carbon::instance($month);
        $from = $month->copy()->startOfMonth();
        $to = $month->copy()->endOfMonth();
        $rows = $this->report->rows($from, $to, ['business' => $business->id]);

        return [
            'business' => $business,
            'month' => $from->format('F Y'),
            'from' => $from, 'to' => $to,
            'totals' => $this->totals($rows),
            'weeks' => $rows->groupBy(fn ($r) => $r['date']->copy()->startOfWeek(Carbon::MONDAY)->toDateString())
                ->map(fn (Collection $week, string $start) => ['label' => Carbon::parse($start)->format('M j').' – '.Carbon::parse($start)->addDays(6)->format('M j')] + $this->totals($week))
                ->sortKeys()->values(),
            'runs' => WeeklyPayoutBatch::where('business_id', $business->id)
                ->whereDate('week_start', '<=', $to->toDateString())->whereDate('week_end', '>=', $from->toDateString())
                ->orderBy('week_start')->get(['id', 'pay_run_type_name', 'week_start', 'week_end', 'status', 'total_payout', 'kind']),
            'shows' => $rows->sortBy(fn ($r) => $r['date']->format('Y-m-d').($r['start']?->format('H:i') ?? ''))->values(),
            'generated_at' => now(\App\Support\BusinessTime::zone()),
        ];
    }

    /** @return array{shows:int, sales:float, net:float, tips:float, cogs:float, pay:float, profit:float, transfer:array} */
    private function totals(Collection $rows): array
    {
        $ids = $rows->pluck('id')->all();
        // The transfer counts pay except tips, the same as a pay run's.
        $payNoTips = $ids === [] ? 0.0 : (float) Payout::whereIn('show_id', $ids)->whereNotIn('payout_type', PayRunSchedules::TIP_TYPES)->sum('calculated_payout');

        return [
            'shows' => $rows->count(),
            'sales' => round((float) $rows->sum('sales'), 2),
            'net' => round((float) $rows->sum('whatnot_net'), 2),
            'tips' => round((float) $rows->sum('tips'), 2),
            'cogs' => round((float) $rows->sum('cogs'), 2),
            'pay' => round((float) $rows->sum('payouts'), 2),
            'profit' => round((float) $rows->sum('profit'), 2),
            'incomplete' => $rows->filter(fn ($r) => $r['incomplete'] !== [])->count(),
            'transfer' => BusinessTransfer::fromTotals($rows->count(), (float) $rows->sum('cogs'), (float) $rows->sum('whatnot_net'), $payNoTips),
        ];
    }

    public function pdf(Business $business, \Carbon\CarbonInterface $month): \Barryvdh\DomPDF\PDF
    {
        return Pdf::loadView('pdf.business-statement', $this->build($business, $month))->setPaper('a4', 'portrait');
    }

    public function filename(Business $business, \Carbon\CarbonInterface $month): string
    {
        return str($business->name)->slug().'-statement-'.$month->format('Y-m').'.pdf';
    }
}
