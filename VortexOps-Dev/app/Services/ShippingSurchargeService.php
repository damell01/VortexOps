<?php

namespace App\Services;

use App\Models\Payout;
use App\Models\Setting;
use App\Models\Show;
use App\Models\ShippingSurcharge;
use App\Models\Streamer;
use Illuminate\Support\Facades\DB;

class ShippingSurchargeService
{
    public function getRate(): float
    {
        return (float) Setting::get('shipping_surcharge_rate', 4.00);
    }

    public function getThreshold(): float
    {
        return (float) Setting::get('shipping_surcharge_threshold', 500.00);
    }

    /**
     * Create (or update) a ShippingSurcharge record for a given show + streamer.
     */
    public function createForShow(Show $show, Streamer $streamer, int $packageCount, ?string $notes = null): ShippingSurcharge
    {
        $rate      = $this->getRate();
        $threshold = $this->getThreshold();
        $total     = round($packageCount * $rate, 2);

        return ShippingSurcharge::updateOrCreate(
            [
                'show_id'     => $show->id,
                'streamer_id' => $streamer->id,
            ],
            [
                'package_count'        => $packageCount,
                'rate_per_package'     => $rate,
                'threshold_amount'     => $threshold,
                'total_amount'         => $total,
                'deducted_from_payout' => false,
                'notes'                => $notes,
            ]
        );
    }

    /**
     * Apply pending shipping surcharges to matching payouts for a show.
     * Deducts total_amount from calculated_payout and marks deducted_from_payout = true.
     */
    public function applyToPayouts(Show $show): void
    {
        DB::transaction(function () use ($show) {
            $surcharges = ShippingSurcharge::where('show_id', $show->id)
                ->where('deducted_from_payout', false)
                ->get();

            foreach ($surcharges as $surcharge) {
                $payout = Payout::where('show_id', $show->id)
                    ->where('streamer_id', $surcharge->streamer_id)
                    ->first();

                if (! $payout) {
                    continue;
                }

                $current  = (float) $payout->calculated_payout;
                $deducted = (float) ($payout->shipping_surcharge_deducted ?? 0);
                $amount   = (float) $surcharge->total_amount;

                $payout->calculated_payout          = max(0, $current - $amount);
                $payout->shipping_surcharge_deducted = $deducted + $amount;
                $payout->save();

                $surcharge->deducted_from_payout = true;
                $surcharge->save();
            }
        });
    }
}
