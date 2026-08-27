<?php

namespace App\Support;

use App\Models\Setting;

/**
 * The streamer profit-share formula, in one place.
 *
 * Taken from the Calculations tab every streamer fills in after a show. Its
 * cell H998 reads ROUND(shipments * 2.1 + 80 * hours, 2), and the cell beside
 * it is labelled "Burden Rate":
 *
 *     burden   = shipments × rate_per_shipment  +  hours × rate_per_hour
 *     net rev  = gross revenue − product cost − burden
 *     earnings = net rev × the streamer's profit share %
 *
 * Worked against the paperwork it came from:
 *
 *     80 × $2.10 + 4.45 × $80              = $524.00
 *     $7,371.10 − $3,392.00 − $524.00      = $3,455.10
 *     $3,455.10 × 8%                       = $276.41
 *
 * The rates are global rather than per person because that one sheet is what
 * everybody uses; the percentage is per person and stays on their record.
 *
 * A class rather than a few lines inside PayoutService because two services
 * already compute profit share and disagree — PayoutService writes payouts and
 * never knew about the burden, while ProfitShareCalculationService knew about
 * product and shipping cost but does not write payouts. Whatever else changes,
 * they should not be able to disagree about this.
 */
class ProfitShareFormula
{
    public const DEFAULT_PER_SHIPMENT = 2.10;
    public const DEFAULT_PER_HOUR     = 80.00;

    public static function ratePerShipment(): float
    {
        return (float) Setting::get('payroll_burden_per_shipment', self::DEFAULT_PER_SHIPMENT);
    }

    public static function ratePerHour(): float
    {
        return (float) Setting::get('payroll_burden_per_hour', self::DEFAULT_PER_HOUR);
    }

    /** What the show costs the business in shipping and time before any share. */
    public static function burden(float $shipments, float $hours): float
    {
        return round($shipments * self::ratePerShipment() + $hours * self::ratePerHour(), 2);
    }

    /**
     * What the percentage is applied to.
     *
     * Not floored at zero: a show that lost money should say so rather than
     * quietly reading as break-even, and whether a loss reduces someone's pay
     * is a decision for whoever reviews the pay run, not for this.
     */
    public static function netRevenue(float $grossRevenue, float $productCost, float $burden): float
    {
        return round($grossRevenue - $productCost - $burden, 2);
    }

    /**
     * A show's full profit-share working, ready to store and to display.
     *
     * Returns every intermediate value, because a payout that cannot show how
     * it was reached is one payroll has to recompute by hand to trust.
     *
     * @param  float  $percentage  Whole percent, as it is stored on the streamer (8 means 8%).
     * @return array{shipments:float,hours:float,rate_per_shipment:float,rate_per_hour:float,burden:float,gross_revenue:float,product_cost:float,net_revenue:float,percentage:float,earnings:float}
     */
    public static function forShow(
        float $grossRevenue,
        float $productCost,
        float $hours,
        float $shipments,
        float $percentage,
    ): array {
        $burden = self::burden($shipments, $hours);
        $net    = self::netRevenue($grossRevenue, $productCost, $burden);

        return [
            'shipments'         => $shipments,
            'hours'             => $hours,
            'rate_per_shipment' => self::ratePerShipment(),
            'rate_per_hour'     => self::ratePerHour(),
            'burden'            => $burden,
            'gross_revenue'     => round($grossRevenue, 2),
            'product_cost'      => round($productCost, 2),
            'net_revenue'       => $net,
            'percentage'        => $percentage,
            'earnings'          => round($net * ($percentage / 100), 2),
        ];
    }

    /** The working, in the order a person reads it. */
    public static function explain(array $working): string
    {
        return sprintf(
            'Burden %s shipments × $%s + %s hrs × $%s = $%s. '
            . 'Net rev $%s − $%s − $%s = $%s. Share %s%% = $%s.',
            rtrim(rtrim(number_format($working['shipments'], 2), '0'), '.'),
            number_format($working['rate_per_shipment'], 2),
            rtrim(rtrim(number_format($working['hours'], 2), '0'), '.'),
            number_format($working['rate_per_hour'], 2),
            number_format($working['burden'], 2),
            number_format($working['gross_revenue'], 2),
            number_format($working['product_cost'], 2),
            number_format($working['burden'], 2),
            number_format($working['net_revenue'], 2),
            rtrim(rtrim(number_format($working['percentage'], 2), '0'), '.'),
            number_format($working['earnings'], 2),
        );
    }
}
