<?php

namespace App\Services;

use Illuminate\Support\Carbon;

/** Builds the daily and weekly P&L summaries from ProfitLossReport. */
class ProfitLossSummary
{
    public function __construct(private ProfitLossReport $report) {}

    /** @param  'day'|'week'  $period */
    public function build(string $period, Carbon $anchor): array
    {
        [$from, $to, $label] = $period === 'day'
            ? [$anchor->copy()->startOfDay(), $anchor->copy()->startOfDay(), $anchor->format('D M j, Y')]
            : [$anchor->copy()->startOfWeek(), $anchor->copy()->endOfWeek()->startOfDay(), $anchor->copy()->startOfWeek()->format('M j').' – '.$anchor->copy()->endOfWeek()->format('M j, Y')];

        $rows = $this->report->rows($from, $to);

        return [
            'label' => $label,
            'period' => $period,
            'anchor' => $from->toDateString(),
            'totals' => $this->report->totals($rows),
            'streamers' => $this->report->breakdown($rows, 'streamer')->map(fn ($r) => [
                'label' => $r['label'], 'shows' => $r['shows'], 'sales' => $r['sales'], 'profit' => $r['profit'], 'losing' => $r['losing'],
            ])->all(),
            'shows' => $rows->sortBy('profit')->map(fn ($r) => [
                'id' => $r['id'], 'title' => $r['title'], 'date' => $r['date']?->format('D M j'), 'streamer' => $r['streamer'],
                'channel' => $r['channel'], 'sales' => $r['sales'], 'cogs' => $r['cogs'], 'payouts' => $r['payouts'],
                'profit' => $r['profit'], 'incomplete' => $r['incomplete'],
            ])->values()->all(),
        ];
    }
}
