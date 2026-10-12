<?php

namespace App\Services;

use App\Filament\Widgets\NeedsAttentionWidget;
use App\Models\Show;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * The numbers behind the Monday owner email: last week against the week
 * before, by channel, top streamers, which title offers earned most per
 * hour, and what is waiting on the owner.
 *
 * Plain figures, no AI model — the email is generated from these with a
 * fixed template, so it is instant and never invents anything.
 */
class WeeklyOwnerSummary
{
    /** @return array<string, mixed> */
    public function build(Carbon $weekStart): array
    {
        $start = $weekStart->copy()->startOfWeek();
        $end = $start->copy()->endOfWeek();
        $prevStart = $start->copy()->subWeek();

        $shows = $this->shows($start, $end);
        $previous = $this->shows($prevStart, $prevStart->copy()->endOfWeek());
        $totals = $this->totals($shows);
        $prevTotals = $this->totals($previous);

        $tags = collect(app(ShowFormatAnalytics::class)->compareTitleTags($start, $end)['rows'])
            ->filter(fn (array $row) => $row['tag'] !== null && $row['shows'] >= 2 && $row['gross_per_hour'] !== null)
            ->take(3)->values()->all();

        return [
            'week_label' => $start->format('M j').' – '.$end->format('M j, Y'),
            'totals' => $totals,
            'change' => [
                'gross_pct' => $this->pct($totals['gross'], $prevTotals['gross']),
                'net_pct' => $this->pct($totals['net'], $prevTotals['net']),
                'shows' => $totals['shows'] - $prevTotals['shows'],
            ],
            'channels' => $shows->groupBy(fn (Show $s) => $s->channel?->name ?? 'No channel')
                ->map(fn (Collection $group, string $name) => ['name' => $name] + $this->totals($group))
                ->sortByDesc('gross')->values()->all(),
            'streamers' => $this->topStreamers($shows),
            'tags' => $tags,
            'attention' => $this->attention(),
        ];
    }

    private function shows(Carbon $from, Carbon $to): Collection
    {
        return Show::query()
            ->whereNotIn('status', ['cancelled'])
            ->whereBetween('show_date', [$from->toDateString(), $to->copy()->endOfDay()->toDateTimeString()])
            ->with(['channel:id,name', 'streamers:id,name'])
            ->get(['id', 'whatnot_channel_id', 'gross_revenue', 'whatnot_net', 'completed_earnings', 'show_duration', 'units_sold']);
    }

    /** @return array{shows: int, gross: float, net: float, hours: float, units: int} */
    private function totals(Collection $shows): array
    {
        return [
            'shows' => $shows->count(),
            'gross' => round((float) $shows->sum(fn (Show $s) => (float) $s->gross_revenue), 2),
            // Settled earnings, or Whatnot's estimate until a show settles.
            'net' => round((float) $shows->sum(fn (Show $s) => $s->completed_earnings !== null ? (float) $s->completed_earnings : (float) $s->whatnot_net), 2),
            'hours' => round((float) $shows->sum(fn (Show $s) => (float) $s->show_duration) / 60, 1),
            'units' => (int) $shows->sum(fn (Show $s) => (int) $s->units_sold),
        ];
    }

    /** A shared show counts in full for each streamer on it. */
    private function topStreamers(Collection $shows): array
    {
        $by = [];
        foreach ($shows as $show) {
            foreach ($show->streamers as $streamer) {
                $by[$streamer->id] ??= ['name' => $streamer->name, 'gross' => 0.0, 'shows' => 0];
                $by[$streamer->id]['gross'] += (float) $show->gross_revenue;
                $by[$streamer->id]['shows']++;
            }
        }

        return collect($by)->sortByDesc('gross')->take(5)->values()->all();
    }

    /** The owner's Needs Attention list, as the dashboard shows it. */
    private function attention(): array
    {
        $owner = User::where('email', config('app.owner_email') ?: 'dbellcreations@gmail.com')->first();
        $previous = Auth::user();

        try {
            if ($owner) {
                Auth::setUser($owner);
            }

            return collect((new NeedsAttentionWidget)->summaryItems())
                ->map(fn (array $item) => ['count' => $item['count'], 'label' => $item['label']])
                ->all();
        } catch (\Throwable) {
            return [];
        } finally {
            if ($previous) {
                Auth::setUser($previous);
            } elseif ($owner) {
                Auth::forgetUser();
            }
        }
    }

    private function pct(float $now, float $before): ?float
    {
        return $before > 0 ? round(($now - $before) / $before * 100, 1) : null;
    }
}
