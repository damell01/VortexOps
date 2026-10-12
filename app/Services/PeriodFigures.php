<?php

namespace App\Services;

use App\Models\Show;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Business figures for any date range, overall and per channel.
 *
 * Profit uses Show::profitAndLoss() — the same rule as the show page:
 * net (settled Whatnot earnings, or Whatnot's estimate until a show settles)
 * plus tips, less cost of goods (approved items from the streamer report) and
 * streamer pay. Whatnot fees are gross less that net. Giveaway spend is what
 * Whatnot reports for the show; it is shown for reference and not subtracted,
 * because the cost of giveaway items is already in cost of goods.
 */
class PeriodFigures
{
    /** @return array<string, float|int|null> */
    public function totals(Carbon $from, Carbon $to, ?int $channelId = null): array
    {
        return $this->sum($this->shows($from, $to, $channelId));
    }

    /** @return list<array<string, mixed>> one row per channel, most profit first */
    public function byChannel(Carbon $from, Carbon $to, ?int $channelId = null): array
    {
        return $this->shows($from, $to, $channelId)
            ->groupBy(fn (Show $s) => $s->channel?->name ?? 'No channel')
            ->map(fn (Collection $shows, string $name) => ['channel' => $name] + $this->sum($shows))
            ->sortByDesc('profit')->values()->all();
    }

    private function shows(Carbon $from, Carbon $to, ?int $channelId): Collection
    {
        return Show::query()
            ->whereNotIn('status', ['cancelled'])
            ->whereBetween('show_date', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->when($channelId, fn ($q) => $q->where('whatnot_channel_id', $channelId))
            ->with(['channel:id,name', 'latestDeductionRequest.lines'])
            ->withSum('payouts', 'calculated_payout')
            ->get();
    }

    /** @return array<string, float|int|null> */
    private function sum(Collection $shows): array
    {
        $t = ['shows' => $shows->count(), 'gross' => 0.0, 'net' => 0.0, 'fees' => 0.0, 'tips' => 0.0, 'cogs' => 0.0,
            'pay' => 0.0, 'profit' => 0.0, 'giveaways' => 0.0, 'units' => 0, 'hours' => 0.0];

        foreach ($shows as $show) {
            $pl = $show->profitAndLoss();
            $t['gross'] += $pl['gross'];
            $t['net'] += $pl['net'];
            // Fees only where Whatnot has reported a net; otherwise there is
            // nothing to subtract from and the show would look all-fee.
            $t['fees'] += $pl['net'] > 0 ? max(0, $pl['gross'] - $pl['net']) : 0;
            $t['tips'] += $pl['tips'];
            $t['cogs'] += $pl['cogs'];
            $t['pay'] += $pl['payouts'];
            $t['profit'] += $pl['margin'];
            $t['giveaways'] += (float) $show->giveaway_spend;
            $t['units'] += (int) $show->units_sold;
            $t['hours'] += (float) $show->show_duration / 60;
        }

        foreach (['gross', 'net', 'fees', 'tips', 'cogs', 'pay', 'profit', 'giveaways'] as $money) {
            $t[$money] = round($t[$money], 2);
        }
        $t['hours'] = round($t['hours'], 1);
        $t['gross_per_hour'] = $t['hours'] > 0 ? round($t['gross'] / $t['hours'], 2) : null;
        $t['margin_pct'] = ($t['net'] + $t['tips']) > 0 ? round($t['profit'] / ($t['net'] + $t['tips']) * 100, 1) : null;
        $t['profit_per_show'] = $t['shows'] > 0 ? round($t['profit'] / $t['shows'], 2) : null;

        return $t;
    }
}
