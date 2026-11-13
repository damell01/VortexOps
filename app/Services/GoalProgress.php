<?php

namespace App\Services;

use App\Models\Business;
use App\Models\Goal;
use App\Models\WhatnotChannel;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * How far each month's goals have got. Actuals come from that month's shows
 * (not cancelled; "shows streamed" counts those up to today): a channel goal counts its channel's shows, a business goal
 * the shows its people streamed. "Expected" is where a straight line through
 * the month would be today, so a bar can say ahead or behind.
 */
final class GoalProgress
{
    /** @return Collection<int, array> */
    public function forMonth(Carbon|string $month): Collection
    {
        $start = Carbon::parse($month)->startOfMonth();
        $end = $start->copy()->endOfMonth();
        $goals = Goal::query()->whereDate('month', $start->toDateString())->orderBy('scope')->orderBy('scope_id')->orderBy('metric')->get();
        if ($goals->isEmpty()) return collect();

        $today = now();
        $elapsed = $today->lt($start) ? 0.0 : ($today->gt($end) ? 1.0 : ($today->day / $start->daysInMonth));
        $businesses = Business::query()->pluck('name', 'id');
        $channels = WhatnotChannel::query()->pluck('name', 'id');

        return $goals->map(function (Goal $goal) use ($start, $end, $elapsed, $businesses, $channels) {
            $actual = $this->actual($goal, $start, $end);
            $target = (float) $goal->target;
            $pct = $target > 0 ? round($actual / $target * 100, 1) : 0.0;
            $expected = round($elapsed * 100, 1);
            $status = $pct >= 100 ? 'done' : ($elapsed >= 1 ? 'missed' : ($pct + 5 >= $expected ? 'on_track' : 'behind'));

            return [
                'id' => $goal->id,
                'label' => Goal::METRICS[$goal->metric] ?? $goal->metric,
                'scope' => match ($goal->scope) {
                    'business' => $businesses[$goal->scope_id] ?? 'Business',
                    'channel' => $channels[$goal->scope_id] ?? 'Channel',
                    default => 'Everyone',
                },
                'metric' => $goal->metric,
                'money' => $goal->isMoney(),
                'target' => $target,
                'actual' => $actual,
                'pct' => $pct,
                'expected' => $expected,
                'status' => $status,
            ];
        });
    }

    /**
     * This month's pace for one metric: the goal, what is in so far, and where
     * the month lands if the rest goes at the same daily rate. A channel goal
     * is used when one is set for that channel, else the everyone goal.
     *
     * @return array{label:string, scope:string, metric:string, money:bool, target:float, actual:float, projected:float, expected:float, per_day:float, pct:float, status:string, month:string, days_left:int}|null
     */
    public function pace(string $metric = 'whatnot_net', ?int $channelId = null): ?array
    {
        $start = now()->startOfMonth();
        $goal = null;
        if ($channelId) {
            $goal = Goal::query()->whereDate('month', $start->toDateString())->where('metric', $metric)->where('scope', 'channel')->where('scope_id', $channelId)->first();
        }
        $goal ??= Goal::query()->whereDate('month', $start->toDateString())->where('metric', $metric)->where('scope', 'all')->first();
        if (! $goal || (float) $goal->target <= 0) return null;

        $end = $start->copy()->endOfMonth();
        $days = $start->daysInMonth;
        $elapsedDays = min($days, max(1, now()->day));
        $actual = $this->actual($goal, $start, $end);
        $target = (float) $goal->target;
        $projected = $actual / $elapsedDays * $days;
        $expected = $target * $elapsedDays / $days;

        return [
            'label' => Goal::METRICS[$metric] ?? $metric,
            'scope' => $goal->scope === 'channel' ? (WhatnotChannel::query()->whereKey($goal->scope_id)->value('name') ?? 'Channel') : 'Everyone',
            'metric' => $metric,
            'money' => $goal->isMoney(),
            'target' => $target,
            'actual' => $actual,
            'projected' => round($projected, 2),
            'expected' => round($expected, 2),
            'per_day' => round($target / $days, 2),
            'pct' => round($actual / $target * 100, 1),
            'status' => $actual >= $target ? 'done' : ($projected >= $target ? 'ahead' : 'behind'),
            'month' => $start->format('F'),
            'days_left' => $days - $elapsedDays,
        ];
    }

    private function actual(Goal $goal, Carbon $start, Carbon $end): float
    {
        $q = DB::table('shows')
            ->whereBetween('show_date', [$start->toDateString(), $end->toDateString()])
            ->where(fn ($w) => $w->whereNull('status')->orWhere('status', '!=', 'cancelled'));
        if ($goal->scope === 'channel') {
            $q->where('whatnot_channel_id', $goal->scope_id);
        } elseif ($goal->scope === 'business') {
            $q->whereExists(fn ($e) => $e->selectRaw('1')->from('show_streamer')->join('streamers', 'streamers.id', '=', 'show_streamer.streamer_id')
                ->whereColumn('show_streamer.show_id', 'shows.id')->where('streamers.business_id', $goal->scope_id));
        }

        return round((float) match ($goal->metric) {
            'whatnot_net' => $q->sum('whatnot_net'),
            'gross' => $q->sum('gross_revenue'),
            'shows' => $q->where('show_date', '<=', now()->toDateString())->count(),
            'new_buyers' => $q->sum('first_time_buyers'),
            default => 0,
        }, 2);
    }
}
