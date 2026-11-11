<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * What giveaways cost per show against what the show brought in: Whatnot net,
 * buyers and first-time buyers. Cost is the product given away (from the show
 * report, at its cost) plus Whatnot's own giveaway spend. Shows with and
 * without giveaways are compared side by side. Read-only.
 */
final class GiveawayRoi
{
    /**
     * @return array{shows: Collection, with: array, without: array, totals: array}
     */
    public function between(string $from, string $until, ?int $channelId = null): array
    {
        $productCost = DB::table('streamer_log_items as i')
            ->join('streamer_log_entries as e', 'e.id', '=', 'i.streamer_log_entry_id')
            ->where('i.disposition', 'giveaway')
            ->groupBy('e.show_id')
            ->selectRaw('e.show_id, SUM(COALESCE(i.quantity, 0) * COALESCE(i.unit_cost, 0)) as cost, SUM(COALESCE(i.quantity, 0)) as units');

        $rows = DB::table('shows as s')
            ->leftJoinSub($productCost, 'g', 'g.show_id', '=', 's.id')
            ->leftJoin('whatnot_channels as c', 'c.id', '=', 's.whatnot_channel_id')
            ->where('s.show_date', '>=', Carbon::parse($from)->toDateString())
            ->where('s.show_date', '<', Carbon::parse($until)->addDay()->toDateString())
            ->where(fn ($q) => $q->whereNull('s.status')->orWhere('s.status', '!=', 'cancelled'))
            ->when($channelId, fn ($q) => $q->where('s.whatnot_channel_id', $channelId))
            ->orderByDesc('s.show_date')
            ->get(['s.id', 's.title', 's.show_date', 'c.name as channel', 's.gross_revenue', 's.whatnot_net', 's.buyers_count', 's.first_time_buyers',
                's.giveaway_spend', 's.giveaways_count', 'g.cost as product_cost', 'g.units as giveaway_units']);

        $shows = $rows->map(function ($r) {
            $product = round((float) ($r->product_cost ?? 0), 2);
            $spend = round((float) ($r->giveaway_spend ?? 0), 2);
            $cost = round($product + $spend, 2);
            $count = (int) ($r->giveaways_count ?? 0) ?: (int) ($r->giveaway_units ?? 0);
            $newBuyers = $r->first_time_buyers === null ? null : (int) $r->first_time_buyers;
            $net = round((float) ($r->whatnot_net ?? 0), 2);

            return [
                'id' => (int) $r->id, 'title' => (string) $r->title, 'date' => $r->show_date, 'channel' => $r->channel,
                'giveaways' => $count, 'product_cost' => $product, 'whatnot_spend' => $spend, 'cost' => $cost,
                'gross' => round((float) ($r->gross_revenue ?? 0), 2), 'net' => $net,
                'buyers' => $r->buyers_count === null ? null : (int) $r->buyers_count, 'new_buyers' => $newBuyers,
                'cost_per_new_buyer' => $cost > 0 && $newBuyers ? round($cost / $newBuyers, 2) : null,
                'net_per_dollar' => $cost > 0 ? round($net / $cost, 2) : null,
                'has_giveaways' => $cost > 0 || $count > 0,
            ];
        });

        $summary = function (Collection $set): array {
            $n = $set->count();

            return [
                'shows' => $n,
                'avg_net' => $n ? round((float) $set->avg('net'), 2) : 0.0,
                'avg_new_buyers' => $n ? round((float) $set->whereNotNull('new_buyers')->avg('new_buyers'), 1) : 0.0,
                'avg_cost' => $n ? round((float) $set->avg('cost'), 2) : 0.0,
            ];
        };
        $with = $shows->where('has_giveaways', true);
        $cost = round((float) $with->sum('cost'), 2);
        $newBuyers = (int) $with->sum('new_buyers');

        return [
            'shows' => $shows->values(),
            'with' => $summary($with),
            'without' => $summary($shows->where('has_giveaways', false)),
            'totals' => [
                'cost' => $cost,
                'product_cost' => round((float) $with->sum('product_cost'), 2),
                'whatnot_spend' => round((float) $with->sum('whatnot_spend'), 2),
                'giveaways' => (int) $with->sum('giveaways'),
                'new_buyers' => $newBuyers,
                'cost_per_new_buyer' => $cost > 0 && $newBuyers ? round($cost / $newBuyers, 2) : null,
            ],
        ];
    }
}
