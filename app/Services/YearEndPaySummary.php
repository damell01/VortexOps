<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * What each person was paid in a calendar year, for year-end tax forms
 * (1099s). A run counts in the year it was marked paid (falling back to its
 * pay period's end when no paid date was recorded); only runs marked paid
 * count. Read-only.
 */
final class YearEndPaySummary
{
    /**
     * @return Collection<int, array{streamer_id:int, name:string, legal_name:?string, email:?string, business:?string, kind:string,
     *   total:float, tips:float, other:float, deductions:float, runs:int, lines:int, first_paid:?string, last_paid:?string}>
     */
    public function for(int $year, float $minimum = 0): Collection
    {
        $runs = DB::table('weekly_payout_batches as b')->where('b.status', 'paid')
            ->select('b.id', 'b.week_end')
            ->selectRaw("COALESCE((SELECT MIN(s.created_at) FROM pay_run_report_snapshots s WHERE s.weekly_payout_batch_id = b.id AND s.stage = 'paid'), b.updated_at) as paid_at")
            ->get()
            ->filter(fn ($r) => (int) substr((string) ($r->paid_at ?: $r->week_end), 0, 4) === $year)
            ->keyBy('id');
        if ($runs->isEmpty()) return collect();

        $lines = DB::table('payouts as p')
            ->join('streamers as s', 's.id', '=', 'p.streamer_id')
            ->leftJoin('businesses as biz', 'biz.id', '=', 's.business_id')
            ->whereIn('p.weekly_payout_batch_id', $runs->keys())
            ->whereNotNull('p.streamer_id')
            ->get(['p.streamer_id', 'p.weekly_payout_batch_id', 'p.payout_type', 'p.calculated_payout', 'p.tips_included',
                's.name', 's.legal_name', 's.email', 's.member_type', 'biz.name as business']);

        return $lines->groupBy('streamer_id')->map(function (Collection $rows) use ($runs) {
            $first = $rows->first();
            $dates = $rows->map(fn ($r) => (string) ($runs[$r->weekly_payout_batch_id]->paid_at ?: $runs[$r->weekly_payout_batch_id]->week_end))->sort()->values();

            return [
                'streamer_id' => (int) $first->streamer_id,
                'name' => (string) $first->name,
                'legal_name' => $first->legal_name ?: null,
                'email' => $first->email ?: null,
                'business' => $first->business,
                'kind' => $first->member_type === 'fulfillment' ? 'Fulfillment' : 'Streamer',
                'total' => round((float) $rows->sum('calculated_payout'), 2),
                'tips' => round((float) $rows->where('payout_type', 'show_tips')->sum('calculated_payout') + (float) $rows->where('payout_type', '!=', 'show_tips')->sum('tips_included'), 2),
                'other' => round((float) $rows->whereIn('payout_type', ['other_payment', OtherPayService::TYPE])->where('calculated_payout', '>', 0)->sum('calculated_payout'), 2),
                'deductions' => round((float) $rows->where('calculated_payout', '<', 0)->sum('calculated_payout'), 2),
                'runs' => $rows->pluck('weekly_payout_batch_id')->unique()->count(),
                'lines' => $rows->count(),
                'first_paid' => substr((string) $dates->first(), 0, 10) ?: null,
                'last_paid' => substr((string) $dates->last(), 0, 10) ?: null,
            ];
        })->filter(fn ($r) => $r['total'] >= $minimum)->sortByDesc('total')->values();
    }
}
