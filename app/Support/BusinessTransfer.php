<?php

namespace App\Support;

use App\Models\Show;
use App\Models\StreamerLogEntry;
use App\Models\WeeklyPayoutBatch;

/**
 * What goes to the business's separate bank account for a pay run's shows:
 *
 *     product cost            what the stock sold on those shows cost
 *   + business revenue        Whatnot net earnings − everything this run pays for them
 *   = transfer
 *
 * Tips runs pay only tips, which are not part of Whatnot's net, so they have
 * no transfer.
 */
class BusinessTransfer
{
    /** @return array{shows:int,product_cost:float,net:float,payouts:float,business_revenue:float,transfer:float}|null */
    public static function forRun(WeeklyPayoutBatch $run): ?array
    {
        if ($run->kind === 'tips') {
            return null;
        }

        $payouts = $run->relationLoaded('payouts') ? $run->payouts : $run->payouts()->get();
        $showIds = $payouts->pluck('show_id')->filter()->unique()->values();
        if ($showIds->isEmpty()) {
            return null;
        }

        $productCost = round((float) StreamerLogEntry::whereIn('show_id', $showIds)->sum('product_cost'), 2);
        $net = round((float) Show::whereIn('id', $showIds)->sum('whatnot_net'), 2);
        $paid = round((float) $payouts->whereNotNull('show_id')->sum('calculated_payout'), 2);
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
