<?php

namespace App\Services;

use App\Models\Show;
use App\Models\Streamer;
use App\Support\BusinessTime;
use App\Support\PaySchedule;
use Illuminate\Support\Carbon;

/**
 * What a streamer is on track to be paid this pay period, show by show.
 *
 * Shows already on a pay run read back what the run says (draft, final or
 * paid); the rest are worked out with the pay run's own calculation, so the
 * estimate matches what the run will produce once the show's numbers are in.
 * Shows still waiting on Whatnot's earnings are listed but not counted.
 */
final class StreamerPayEstimate
{
    public function __construct(private PayoutService $payouts) {}

    /**
     * @return array{start: Carbon, end: Carbon, label: string, days_left: int, rows: list<array>, total: float,
     *     estimate: float, confirmed: float, paid: float, waiting: int, shows: int}
     */
    public function forPeriod(Streamer $streamer, Carbon|string|null $date = null): array
    {
        $date = $date ? Carbon::parse($date) : BusinessTime::local();
        [$start, $end] = PaySchedule::periodFor($date, $streamer->business_id);
        $today = BusinessTime::local()->startOfDay();

        $shows = Show::query()
            ->whereHas('streamers', fn ($q) => $q->where('streamers.id', $streamer->id))
            ->whereBetween('show_date', [$start->toDateString(), $end->toDateString().' 23:59:59'])
            ->whereDate('show_date', '<=', $today->toDateString())
            ->where(fn ($q) => $q->whereNull('status')->orWhere('status', '!=', 'cancelled'))
            ->with(['streamers', 'channel:id,name', 'streamerLogEntry'])
            ->orderBy('show_date')->orderBy('start_time')
            ->get();

        $rows = [];
        foreach ($shows as $show) {
            $pay = $this->payouts->payForShow($show, $streamer);
            $rows[] = [
                'id' => $show->id,
                'title' => $show->title ?: 'Show #'.$show->id,
                'date' => $show->show_date,
                'channel' => $show->channel?->name,
                'amount' => $pay['amount'] ?? null,
                'state' => $pay['state'] ?? 'waiting',
                'notes' => $pay['notes'] ?? 'Waiting on Whatnot\'s earnings for this show',
            ];
        }

        $sum = fn (array $states) => round((float) collect($rows)->whereIn('state', $states)->sum('amount'), 2);

        return [
            'start' => $start, 'end' => $end,
            'label' => PaySchedule::label($start, $end),
            'days_left' => max(0, (int) $today->diffInDays($end->copy()->startOfDay(), false)),
            'rows' => array_reverse($rows),
            'total' => $sum(['estimate', 'draft', 'final', 'paid']),
            'estimate' => $sum(['estimate']),
            'confirmed' => $sum(['draft', 'final']),
            'paid' => $sum(['paid']),
            'waiting' => collect($rows)->where('state', 'waiting')->count(),
            'shows' => count($rows),
        ];
    }
}
