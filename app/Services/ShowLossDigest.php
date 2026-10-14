<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The shows behind the two loss notifications, from the same rows as
 * Profit & Loss so the numbers always agree:
 *
 *  - whatnot_negative: Whatnot's own earnings for the show were below $0
 *    (fees and giveaways ate the sales) — before any cost of ours.
 *  - show_lost_money: the show is finalized (earnings, approved COGS and
 *    final pay all in) and profit after pay is below $0.
 */
class ShowLossDigest
{
    public function __construct(private ProfitLossReport $report) {}

    /** @return Collection<int, array<string, mixed>> */
    public function rows(string $event, Carbon $from, Carbon $to, ?int $business = null): Collection
    {
        $rows = $this->report->rows($from, $to, ['business' => $business]);

        return ($event === 'whatnot_negative'
            ? $rows->filter(fn ($r) => $r['whatnot_net'] < 0)->sortBy('whatnot_net')
            : $rows->filter(fn ($r) => $r['incomplete'] === [] && $r['profit'] < 0)->sortBy('profit')
        )->values();
    }

    /** @return array{event:string,label:string,from:string,to:string,shows:array,count:int,total:float,businesses:int} */
    public function build(string $event, Carbon $from, Carbon $to, ?int $business = null): array
    {
        $rows = $this->rows($event, $from, $to, $business);
        $amount = $event === 'whatnot_negative' ? 'whatnot_net' : 'profit';

        return [
            'event' => $event,
            'label' => \App\Support\NotificationSchedule::rangeLabel($from, $to),
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'count' => $rows->count(),
            'total' => round((float) $rows->sum($amount), 2),
            'businesses' => $rows->pluck('business')->unique()->count(),
            'shows' => $rows->map(fn ($r) => [
                'id' => $r['id'],
                'title' => $r['title'],
                'date' => $r['date']?->format('D M j'),
                'streamer' => $r['streamer'],
                'business' => $r['business'],
                'channel' => $r['channel'],
                'amount' => (float) $r[$amount],
                'earnings' => (float) $r['earnings'],
                'cogs' => (float) $r['cogs'],
                'payouts' => (float) $r['payouts'],
            ])->all(),
        ];
    }
}
