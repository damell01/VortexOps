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
 * Each half of the burden applies only where its rate has been set in
 * Settings. Set neither and there is no burden: net revenue is simply gross
 * minus product cost. The rates below are what the sheet used and what the
 * settings page suggests — they are not applied until somebody saves them.
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
    /**
     * The rates on the sheet this came from, offered as the starting point on
     * the settings page. Suggestions, not defaults — nothing applies them until
     * somebody saves them; see ratePerShipment().
     */
    public const SUGGESTED_PER_SHIPMENT = 2.10;
    public const SUGGESTED_PER_HOUR     = 80.00;

    /**
     * A rate that has been set, or null.
     *
     * Null and zero are the same answer here — don't charge for this — and an
     * unset rate must not quietly fall back to the sheet's number. A burden
     * nobody configured is money coming off somebody's pay because of a
     * constant in the source, which is the situation the settings page exists
     * to end.
     */
    private static function rate(string $key): ?float
    {
        $raw = Setting::get($key);

        if ($raw === null || trim((string) $raw) === '') {
            return null;
        }

        $value = (float) $raw;

        return $value > 0 ? $value : null;
    }

    public static function ratePerShipment(): ?float
    {
        return self::rate('payroll_burden_per_shipment');
    }

    public static function ratePerHour(): ?float
    {
        return self::rate('payroll_burden_per_hour');
    }

    /** Whether a burden is charged at all. */
    public static function hasBurden(): bool
    {
        return self::ratePerShipment() !== null || self::ratePerHour() !== null;
    }

    /**
     * What the show costs the business in shipping and time before any share.
     *
     * Each half is charged only if its rate is set, so an operation that pays
     * per shipment but not for time gets exactly that, and one that has set
     * neither has no burden deducted at all.
     */
    public static function burden(float $shipments, float $hours): float
    {
        return round(
            $shipments * (self::ratePerShipment() ?? 0.0)
            + $hours * (self::ratePerHour() ?? 0.0),
            2,
        );
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
     * @return array{shipments:float,hours:float,rate_per_shipment:?float,rate_per_hour:?float,burden:float,gross_revenue:float,product_cost:float,net_revenue:float,percentage:float,earnings:float}
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

    /**
     * The working, in the order a person reads it.
     *
     * With no burden configured the burden sentence is left out rather than
     * printed as a row of zeros — a line saying "× $0.00" reads like a rate
     * somebody got wrong, not like a charge that does not apply here.
     */
    public static function explain(array $working): string
    {
        $number = fn (float $value): string => rtrim(rtrim(number_format($value, 2), '0'), '.');

        $parts = [];

        if ($working['burden'] > 0 || $working['rate_per_shipment'] !== null || $working['rate_per_hour'] !== null) {
            $components = [];

            if ($working['rate_per_shipment'] !== null) {
                $components[] = sprintf('%s shipments × $%s', $number($working['shipments']), number_format($working['rate_per_shipment'], 2));
            }

            if ($working['rate_per_hour'] !== null) {
                $components[] = sprintf('%s hrs × $%s', $number($working['hours']), number_format($working['rate_per_hour'], 2));
            }

            $parts[] = 'Burden ' . implode(' + ', $components) . ' = $' . number_format($working['burden'], 2) . '.';
            $parts[] = sprintf(
                'Net rev $%s − $%s − $%s = $%s.',
                number_format($working['gross_revenue'], 2),
                number_format($working['product_cost'], 2),
                number_format($working['burden'], 2),
                number_format($working['net_revenue'], 2),
            );
        } else {
            $parts[] = 'No burden configured.';
            $parts[] = sprintf(
                'Net rev $%s − $%s = $%s.',
                number_format($working['gross_revenue'], 2),
                number_format($working['product_cost'], 2),
                number_format($working['net_revenue'], 2),
            );
        }

        $parts[] = sprintf('Share %s%% = $%s.', $number($working['percentage']), number_format($working['earnings'], 2));

        return implode(' ', $parts);
    }
}
