<?php

namespace App\Services;

use App\Models\Payout;
use App\Models\Show;
use App\Models\ShowChangeLog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Shows whose earnings changed after their pay was locked: a finalized or
 * paid run paid a line, then the show (Whatnot net, gross, tips) or its report
 * was updated, and the same calculation now gives a different amount.
 *
 * Only shows touched after the run was finalized are worked out again, and a
 * line someone overrode by hand on the run is left alone. Read-only: it never
 * changes a payout; the difference is for a person to settle on a later run.
 */
final class PayChangedAfterPayout
{
    /** How far back to look at locked pay. */
    public const DAYS = 120;

    public const LOCKED = ['finalized', 'submitted_to_adp', 'paid'];

    /**
     * @return Collection<int, array{show_id:int, show:string, show_date:?string, streamer_id:int, streamer:string, run_id:int, run_status:string,
     *   locked_at:?string, paid:float, now:float, difference:float, lines:list<string>, changes:list<array{field:string,old:?string,new:?string,at:string}>}>
     */
    public function rows(int $limit = 150): Collection
    {
        $since = now()->subDays(self::DAYS);

        // Locked lines for shows touched (show or report) after their run locked.
        $locked = Payout::query()
            ->join('weekly_payout_batches as b', 'b.id', '=', 'payouts.weekly_payout_batch_id')
            ->join('shows', 'shows.id', '=', 'payouts.show_id')
            ->leftJoin('streamer_log_entries as log', 'log.show_id', '=', 'shows.id')
            ->whereIn('b.status', self::LOCKED)
            ->whereNotNull('payouts.streamer_id')
            ->where('shows.show_date', '>=', $since->toDateString())
            ->where(function ($q) {
                $locked = 'COALESCE(b.finalized_at, b.updated_at)';
                $q->whereRaw("shows.updated_at > {$locked}")->orWhereRaw("log.updated_at > {$locked}");
            })
            ->where(fn ($q) => $q->whereNull('payouts.calculation_notes')->orWhere('payouts.calculation_notes', 'not like', '%Manual Pay Run override%'))
            ->orderByDesc('shows.show_date')
            ->limit($limit * 4)
            ->get(['payouts.id', 'payouts.show_id', 'payouts.streamer_id', 'payouts.payout_type', 'payouts.calculated_payout',
                'b.id as run_id', 'b.status as run_status', 'b.finalized_at', 'b.updated_at as run_updated_at']);

        if ($locked->isEmpty()) return collect();

        $shows = Show::query()->whereIn('id', $locked->pluck('show_id')->unique())
            ->with(['streamers', 'streamerLogEntry', 'channel'])->get()->keyBy('id');
        $service = app(PayoutService::class);
        $out = collect();

        foreach ($locked->groupBy('show_id') as $showId => $lines) {
            $show = $shows->get($showId);
            if (! $show) continue;
            try {
                $preview = collect($service->previewForShow($show))->keyBy(fn ($row) => $row['streamer']->id);
            } catch (\Throwable) {
                continue;
            }
            $lockedAt = $lines->map(fn ($l) => $l->finalized_at ?? $l->run_updated_at)->filter()->min();

            foreach ($lines->groupBy('streamer_id') as $streamerId => $paidLines) {
                $row = $preview->get((int) $streamerId);
                // Someone no longer on the show would now get nothing.
                $nowByType = collect($row['lines'] ?? [])->groupBy('payout_type')->map(fn ($g) => round((float) $g->sum('calculated_payout'), 2));
                $paid = 0.0; $now = 0.0; $labels = [];
                foreach ($paidLines->groupBy('payout_type') as $type => $typeLines) {
                    $paidType = round((float) $typeLines->sum('calculated_payout'), 2);
                    $nowType = (float) ($nowByType[$type] ?? 0);
                    $paid += $paidType;
                    $now += $nowType;
                    if (abs($nowType - $paidType) >= 0.01) $labels[] = str_replace('_', ' ', (string) $type);
                }
                $difference = round($now - $paid, 2);
                if (abs($difference) < 0.01) continue;

                $first = $paidLines->first();
                $out->push([
                    'show_id' => (int) $showId, 'show' => (string) $show->title, 'show_date' => $show->show_date?->toDateString(),
                    'streamer_id' => (int) $streamerId, 'streamer' => (string) ($row['streamer']->name ?? \App\Models\Streamer::query()->whereKey($streamerId)->value('name')),
                    'run_id' => (int) $first->run_id, 'run_status' => (string) $first->run_status,
                    'locked_at' => $lockedAt ? Carbon::parse($lockedAt)->toIso8601String() : null,
                    'paid' => round($paid, 2), 'now' => round($now, 2), 'difference' => $difference, 'lines' => $labels,
                    'changes' => $this->changesSince((int) $showId, $lockedAt),
                ]);
            }
            if ($out->count() >= $limit) break;
        }

        return $out->sortByDesc(fn ($r) => abs($r['difference']))->values();
    }

    private const CACHE = 'payroll:pay-changed-after-payout';

    /**
     * The last known count, for badges and the overview card. Never works it
     * out on a page load: the hourly check and the page itself store it.
     */
    public function count(): ?int
    {
        $value = \Illuminate\Support\Facades\Cache::get(self::CACHE);

        return $value === null ? null : (int) $value;
    }

    /** Work the list out and remember how many lines there are. */
    public function refresh(): Collection
    {
        $rows = $this->rows();
        \Illuminate\Support\Facades\Cache::put(self::CACHE, $rows->count(), now()->addHours(3));

        return $rows;
    }

    /** What the change log says moved on the show after its pay locked. */
    private function changesSince(int $showId, mixed $lockedAt): array
    {
        if (! $lockedAt || ! class_exists(ShowChangeLog::class)) return [];

        return ShowChangeLog::query()->where('show_id', $showId)->where('created_at', '>', Carbon::parse($lockedAt))
            ->whereIn('field_name', ['gross_revenue', 'whatnot_net', 'whatnot_fees', 'tips', 'units_sold'])
            ->orderBy('created_at')->limit(10)->get(['field_name', 'old_value', 'new_value', 'created_at'])
            ->map(fn ($c) => ['field' => (string) $c->field_name, 'old' => $c->old_value, 'new' => $c->new_value, 'at' => (string) $c->created_at])
            ->all();
    }
}
