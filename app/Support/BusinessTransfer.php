<?php

namespace App\Support;

use App\Models\Show;
use App\Models\StreamerLogEntry;
use App\Models\WeeklyPayoutBatch;

/**
 * What goes to the business's separate bank account for a pay run's shows:
 *
 *     product cost            what the stock sold on those shows cost
 *   + business revenue        Whatnot net earnings − everything paid for them
 *   = transfer
 *
 * A show's pay can be split across runs: wages weekly, profit share monthly,
 * tips monthly. So the transfer belongs to the run that pays a show's wages
 * (or an all-pay run), and counts all of that show's pay except tips, which
 * aren't part of Whatnot's net — whichever run pays it. Profit-share and
 * Tips runs have no transfer of their own, so no show is counted twice.
 */
class BusinessTransfer
{
    /** @return array{shows:int,product_cost:float,net:float,payouts:float,business_revenue:float,transfer:float}|null */
    public static function forRun(WeeklyPayoutBatch $run): ?array
    {
        if (in_array($run->kind, ['tips', 'share'], true)) {
            return null;
        }

        $payouts = $run->relationLoaded('payouts') ? $run->payouts : $run->payouts()->get();
        $showIds = $payouts->pluck('show_id')->filter()->unique()->values();
        if ($showIds->isEmpty()) {
            return null;
        }

        $productCost = round((float) StreamerLogEntry::whereIn('show_id', $showIds)->sum('product_cost'), 2);
        $net = round((float) Show::whereIn('id', $showIds)->sum('whatnot_net'), 2);
        $paid = round((float) \App\Models\Payout::query()
            ->whereIn('show_id', $showIds)
            ->whereNotIn('payout_type', \App\Support\PayRunSchedules::TIP_TYPES)
            ->sum('calculated_payout'), 2);
        $revenue = round($net - $paid, 2);

        return [
            'shows' => $showIds->count(),
            'product_cost' => $productCost,
            'net' => $net,
            'payouts' => $paid,
            'business_revenue' => $revenue,
            'transfer' => round($productCost + $revenue, 2),
        ];
    }
}
