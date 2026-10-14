<?php

namespace App\Services;

use App\Models\Business;
use App\Models\Show;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Profit & loss across shows, for the admin report and the summaries.
 *
 * Every number per show is Show::profitAndLoss() — the same figure the show
 * page's P&L card shows — so the report and the show can never disagree:
 * earnings (Whatnot net, or completed earnings once settled) plus tips, less
 * approved COGS, less streamer pay.
 *
 * A show missing a piece is still counted, and marked Incomplete with the
 * reason, because its profit will move when that piece arrives.
 */
class ProfitLossReport
{
    public const SLOTS = [
        'morning' => 'Morning (5am–noon)',
        'afternoon' => 'Afternoon (noon–5pm)',
        'evening' => 'Evening (5–9pm)',
        'late' => 'Late night (9pm–5am)',
    ];

    /**
     * @param  array{channel?:?int,business?:?int,streamer?:?int}  $filters
     * @return Collection<int, array<string, mixed>> one row per show
     */
    public function rows(Carbon $from, Carbon $to, array $filters = []): Collection
    {
        $shows = Show::query()
            ->where('is_operational', true)
            ->where('status', '!=', 'cancelled')
            ->whereBetween('show_date', [$from->toDateString(), $to->toDateString().' 23:59:59'])
            // Shows that have not aired yet have no results to count.
            ->whereDate('show_date', '<=', \App\Support\BusinessTime::local()->toDateString())
            ->when($filters['channel'] ?? null, fn ($q, $id) => $q->where('whatnot_channel_id', $id))
            ->when($filters['streamer'] ?? null, fn ($q, $id) => $q->whereHas('streamers', fn ($s) => $s->where('streamers.id', $id)))
            ->when($filters['business'] ?? null, fn ($q, $id) => $q->whereHas('streamers', fn ($s) => $s->where('business_id', $id)))
            ->with([
                'channel:id,name',
                'streamers:id,name,business_id,streamer_type',
                'latestDeductionRequest.lines:id,deduction_request_id,line_total',
                'streamerLogEntry:id,show_id,streamer_id,status,submitted_at,hours_streamed,product_cost',
                'payouts:id,show_id,streamer_id,status,calculated_payout,weekly_payout_batch_id',
                'payouts.batch:id,status',
            ])
            ->withSum('payouts', 'calculated_payout')
            ->orderBy('show_date')->orderBy('start_time')
            ->get();

        $businesses = Business::pluck('name', 'id');

        return $shows->map(function (Show $show) use ($businesses) {
            $pl = $show->profitAndLoss();
            $primary = $show->streamers->firstWhere('pivot.is_primary', true) ?? $show->streamers->first();
            $entry = $show->streamerLogEntry;

            $missing = [];
            if ($pl['gross'] > 0 && $pl['net'] <= 0) {
                $missing[] = 'Earnings not in yet';
            }
            if (! $show->latestDeductionRequest) {
                $missing[] = $entry?->submitted_at ? 'COGS not approved yet' : 'No show report yet';
            }
            if ($show->streamers->isNotEmpty() && $show->payouts->isEmpty()) {
                $missing[] = 'Pay not calculated';
            } elseif ($show->payouts->contains(fn ($p) => $p->status !== 'paid' && ! in_array($p->batch?->status, ['finalized', 'submitted_to_adp', 'paid'], true))) {
                $missing[] = 'Pay not final';
            }
            if ($show->streamers->isEmpty()) {
                $missing[] = 'No streamer assigned';
            }

            $earnings = round($pl['net'] + $pl['tips'], 2);
            $payFinal = $show->payouts->isNotEmpty() && ! $show->payouts->contains(fn ($p) => $p->status !== 'paid' && ! in_array($p->batch?->status, ['finalized', 'submitted_to_adp', 'paid'], true));
            $payPaid = $show->payouts->isNotEmpty() && $show->payouts->every(fn ($p) => $p->status === 'paid' || $p->batch?->status === 'paid');

            return [
                'id' => $show->id,
                'date' => $show->show_date?->copy(),
                'start' => $show->start_time?->copy(),
                'title' => $show->title ?: 'Show #'.$show->id,
                'channel_id' => $show->whatnot_channel_id,
                'channel' => $show->channel?->name ?? 'No channel',
                'streamer_id' => $primary?->id,
                'streamer' => $primary?->name ?? 'Unassigned',
                'cohosts' => $show->streamers->where('id', '!=', $primary?->id)->pluck('name')->all(),
                'business_id' => $primary?->business_id,
                'business' => $primary?->business_id ? ($businesses[$primary->business_id] ?? 'Business') : 'No business',
                'sales' => $pl['gross'],
                'earnings' => $earnings,
                // Whatnot's own figure (completed earnings once settled, else
                // the estimate) before tips — negative means fees and giveaways
                // ate the sales before any cost of ours.
                'whatnot_net' => $pl['net'],
                'tips' => $pl['tips'],
                'cogs_recorded' => (bool) $show->latestDeductionRequest,
                'pay_final' => $payFinal,
                'pay_paid' => $payPaid,
                'synced_at' => $show->last_analytics_synced_at ?? $show->last_synced_at,
                'cogs' => $pl['cogs'],
                'payouts' => $pl['payouts'],
                'profit' => $pl['margin'],
                'margin_pct' => $pl['margin_pct'],
                'hours' => $entry?->hours_streamed !== null ? (float) $entry->hours_streamed : null,
                'units' => (int) $show->units_sold,
                'incomplete' => $missing,
                'weekday' => $show->show_date?->format('l'),
                'slot' => $this->slot($show),
            ];
        })->values();
    }

    /** @return array<string, float|int> */
    public function totals(Collection $rows): array
    {
        $earnings = (float) $rows->sum('earnings');
        $profit = (float) $rows->sum('profit');
        $hours = (float) $rows->sum(fn ($r) => $r['hours'] ?? 0);

        return [
            'shows' => $rows->count(),
            'sales' => round((float) $rows->sum('sales'), 2),
            'earnings' => round($earnings, 2),
            'cogs' => round((float) $rows->sum('cogs'), 2),
            'payouts' => round((float) $rows->sum('payouts'), 2),
            'profit' => round($profit, 2),
            'margin_pct' => $earnings > 0 ? round($profit / $earnings * 100, 1) : 0.0,
            'losing' => $rows->where('profit', '<', 0)->count(),
            'whatnot_negative' => $rows->where('whatnot_net', '<', 0)->count(),
            'whatnot_negative_sum' => round((float) $rows->where('whatnot_net', '<', 0)->sum('whatnot_net'), 2),
            'finalized' => $rows->filter(fn ($r) => $r['incomplete'] === [])->count(),
            'finalized_profit' => round((float) $rows->filter(fn ($r) => $r['incomplete'] === [])->sum('profit'), 2),
            'finalized_losing' => $rows->filter(fn ($r) => $r['incomplete'] === [] && $r['profit'] < 0)->count(),
            'missing_cogs' => $rows->filter(fn ($r) => ! $r['cogs_recorded'])->count(),
            'pay_pending' => $rows->filter(fn ($r) => ! $r['pay_final'])->count(),
            'incomplete' => $rows->filter(fn ($r) => $r['incomplete'] !== [])->count(),
            'units' => (int) $rows->sum('units'),
            'hours' => round($hours, 2),
            'profit_per_show' => $rows->count() ? round($profit / $rows->count(), 2) : 0.0,
            'profit_per_hour' => $hours > 0 ? round($profit / $hours, 2) : null,
        ];
    }

    /**
     * Group rows by one dimension and total each group, best first.
     *
     * @param  'streamer'|'channel'|'business'|'weekday'|'slot'  $by
     * @return Collection<int, array<string, mixed>>
     */
    public function breakdown(Collection $rows, string $by): Collection
    {
        $keyed = match ($by) {
            'streamer' => $rows->groupBy(fn ($r) => $r['streamer']),
            'channel' => $rows->groupBy(fn ($r) => $r['channel']),
            'business' => $rows->groupBy(fn ($r) => $r['business']),
            'weekday' => $rows->groupBy(fn ($r) => $r['weekday'] ?? 'Unknown'),
            'slot' => $rows->groupBy(fn ($r) => self::SLOTS[$r['slot']] ?? 'Time not recorded'),
        };

        $out = $keyed->map(fn ($group, $label) => ['label' => (string) $label] + $this->totals($group));

        if ($by === 'weekday') {
            $order = array_flip(['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday']);

            return $out->sortBy(fn ($r) => $order[$r['label']] ?? 9)->values();
        }
        if ($by === 'slot') {
            $order = array_flip(array_values(self::SLOTS));

            return $out->sortBy(fn ($r) => $order[$r['label']] ?? 9)->values();
        }

        return $out->sortByDesc('profit')->values();
    }

    /**
     * Profit per day (or per month for long ranges) for the trend chart.
     *
     * @return Collection<int, array{label:string,key:string,sales:float,profit:float,shows:int}>
     */
    public function trend(Collection $rows, Carbon $from, Carbon $to): Collection
    {
        $monthly = $from->diffInDays($to) > 62;
        $buckets = collect();
        $cursor = $from->copy()->startOfDay();
        while ($cursor->lte($to)) {
            $key = $monthly ? $cursor->format('Y-m') : $cursor->toDateString();
            $buckets[$key] ??= ['key' => $key, 'label' => $monthly ? $cursor->format('M') : $cursor->format('D j'), 'sales' => 0.0, 'profit' => 0.0, 'shows' => 0];
            $monthly ? $cursor->addMonthNoOverflow()->startOfMonth() : $cursor->addDay();
        }
        foreach ($rows as $r) {
            $key = $monthly ? $r['date']?->format('Y-m') : $r['date']?->toDateString();
            if ($key === null || ! isset($buckets[$key])) {
                continue;
            }
            $b = $buckets[$key];
            $b['sales'] = round($b['sales'] + $r['sales'], 2);
            $b['profit'] = round($b['profit'] + $r['profit'], 2);
            $b['shows']++;
            $buckets[$key] = $b;
        }

        return $buckets->values();
    }

    private function slot(Show $show): ?string
    {
        if (! $show->start_time) {
            return null;
        }
        $hour = (int) $show->start_time->format('G');

        return match (true) {
            $hour >= 5 && $hour < 12 => 'morning',
            $hour >= 12 && $hour < 17 => 'afternoon',
            $hour >= 17 && $hour < 21 => 'evening',
            default => 'late',
        };
    }
}
