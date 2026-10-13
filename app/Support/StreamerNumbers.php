<?php

namespace App\Support;

use App\Models\Payout;
use App\Models\Show;
use App\Models\Streamer;
use App\Models\StreamerLogEntry;
use App\Models\TimeEntry;
use Illuminate\Support\Carbon;

/**
 * A streamer's own numbers for a period — what My Shows shows them.
 *
 * Deliberately no cost, profit or loss: streamers see what they sold, the
 * hours they worked and what they were paid, not how a show did for the
 * business.
 */
class StreamerNumbers
{
    /**
     * @return array{shows:int,hours:float,gross:float,units:int,paid:float,awaiting:float}
     */
    public static function for(Streamer $streamer, Carbon $from, Carbon $to): array
    {
        $fromDate = $from->toDateString();
        $toDate = $to->toDateString().' 23:59:59';

        $shows = Show::query()
            ->whereHas('streamers', fn ($q) => $q->where('streamers.id', $streamer->id))
            ->where('status', '!=', 'cancelled')
            ->whereBetween('show_date', [$fromDate, $toDate]);

        $totals = (clone $shows)->selectRaw('COUNT(*) as n, COALESCE(SUM(gross_revenue),0) as gross, COALESCE(SUM(units_sold),0) as units')->first();

        $reportHours = (float) StreamerLogEntry::query()
            ->where('streamer_id', $streamer->id)
            ->whereIn('show_id', (clone $shows)->select('shows.id'))
            ->sum('hours_streamed');

        // Time not on a show (prep, cleanup...) that payroll counts.
        $otherMinutes = $streamer->user_id
            ? (int) TimeEntry::where('user_id', $streamer->user_id)->whereNull('show_id')->whereNotNull('clocked_out_at')
                ->whereBetween('clocked_in_at', [$from->copy()->startOfDay()->utc(), $to->copy()->endOfDay()->utc()])
                ->get()->sum(fn (TimeEntry $e) => $e->payableMinutes())
            : 0;

        $payouts = Payout::query()
            ->where('streamer_id', $streamer->id)
            ->where(fn ($q) => $q
                ->whereHas('show', fn ($s) => $s->whereBetween('show_date', [$fromDate, $toDate]))
                ->orWhere(fn ($w) => $w->whereNull('show_id')->whereHas('batch', fn ($b) => $b->whereBetween('week_end', [$fromDate, $toDate]))))
            ->get(['status', 'calculated_payout']);

        return [
            'shows' => (int) ($totals->n ?? 0),
            'hours' => round($reportHours + $otherMinutes / 60, 2),
            'gross' => round((float) ($totals->gross ?? 0), 2),
            'units' => (int) ($totals->units ?? 0),
            'paid' => round((float) $payouts->where('status', 'paid')->sum('calculated_payout'), 2),
            'awaiting' => round((float) $payouts->where('status', '!=', 'paid')->sum('calculated_payout'), 2),
        ];
    }
}
