<?php

namespace App\Services;

use App\Models\Show;
use Illuminate\Support\Carbon;

/**
 * How the different kinds of break actually compare.
 *
 * Every show used to be recorded identically, so every average was taken
 * across formats that behave nothing alike — a sudden death and a
 * giveaway-heavy night landed in the same mean, and the mean described
 * neither. This groups by what a show was and reports each group beside the
 * others, which is the only form in which the question "is sudden death
 * worth running?" has an answer.
 */
class ShowFormatAnalytics
{
    /**
     * One row per format, plus the shows nobody has classified.
     *
     * @return array<int, array{
     *     format: ?string, label: string, shows: int, avg_net: float, avg_units: float,
     *     avg_giveaways: float, avg_buyers: float, total_net: float,
     *     net_vs_overall_pct: ?float
     * }>
     */
    public function compare(?Carbon $from = null, ?Carbon $until = null, ?int $channelId = null): array
    {
        $query = Show::query()
            // Only shows that actually ran. A cancelled or draft show has no
            // performance to average, and including them drags every format
            // toward zero by however many were abandoned.
            ->whereNotIn('status', ['draft', 'cancelled'])
            ->when($from, fn ($q) => $q->whereDate('show_date', '>=', $from))
            ->when($until, fn ($q) => $q->whereDate('show_date', '<=', $until))
            ->when($channelId, fn ($q) => $q->where('whatnot_channel_id', $channelId));

        $shows = (clone $query)->get([
            'id', 'show_format', 'gross_revenue', 'whatnot_net', 'units_sold',
            'giveaways_count', 'buyers_count',
        ]);

        if ($shows->isEmpty()) {
            return [];
        }

        // Net where it has been synced, gross where it has not. The same rule
        // the show page uses, for the same reason: a show reporting zero
        // during its first hours would drag its whole format down.
        $revenueOf = fn (Show $show) => ((float) $show->whatnot_net) > 0
            ? (float) $show->whatnot_net
            : (float) $show->gross_revenue;

        $overallAvg = $shows->avg($revenueOf);

        $labels = Show::formatLabels();

        return $shows
            ->groupBy(fn (Show $show) => $show->show_format ?? '__none__')
            ->map(function ($group, $key) use ($revenueOf, $overallAvg, $labels) {
                $avgNet = round($group->avg($revenueOf), 2);

                return [
                    'format'        => $key === '__none__' ? null : $key,
                    'label'         => $key === '__none__' ? 'Unclassified' : ($labels[$key] ?? $key),
                    'shows'         => $group->count(),
                    'avg_net'       => $avgNet,
                    'total_net'     => round($group->sum($revenueOf), 2),
                    'avg_units'     => round($group->avg(fn ($s) => (float) $s->units_sold), 1),
                    'avg_giveaways' => round($group->avg(fn ($s) => (float) $s->giveaways_count), 1),
                    'avg_buyers'    => round($group->avg(fn ($s) => (float) $s->buyers_count), 1),
                    // The number the comparison exists for: how this format
                    // does against the average of everything.
                    'net_vs_overall_pct' => $overallAvg > 0
                        ? round((($avgNet - $overallAvg) / $overallAvg) * 100, 1)
                        : null,
                ];
            })
            ->sortByDesc('avg_net')
            ->values()
            ->all();
    }

    /**
     * The average across every show in range, for the row the formats are
     * measured against.
     */
    public function overall(?Carbon $from = null, ?Carbon $until = null, ?int $channelId = null): array
    {
        $rows = $this->compare($from, $until, $channelId);

        if ($rows === []) {
            return ['shows' => 0, 'avg_net' => 0.0, 'total_net' => 0.0, 'classified' => 0];
        }

        $shows = array_sum(array_column($rows, 'shows'));
        $total = array_sum(array_column($rows, 'total_net'));

        $classified = array_sum(array_map(
            fn ($row) => $row['format'] === null ? 0 : $row['shows'],
            $rows,
        ));

        return [
            'shows'      => $shows,
            'avg_net'    => $shows > 0 ? round($total / $shows, 2) : 0.0,
            'total_net'  => round($total, 2),
            'classified' => $classified,
        ];
    }

    /**
     * Earnings per hour by title tag (giveaways, $1 starts, free packs…).
     *
     * A show with several tags counts toward each of them, so the rows are not
     * meant to add up; each is "shows that offered X", set against the
     * per-hour rate of every show in range. Per-hour figures only use shows
     * with a recorded duration. Net is settled earnings, or Whatnot's estimate
     * until a show settles — the same rule as the dashboard.
     *
     * @return array{rows: list<array{tag: ?string, label: string, shows: int, hours: float, gross_per_hour: ?float, net_per_hour: ?float, gross_vs_overall_pct: ?float}>, overall: array{shows: int, hours: float, gross_per_hour: ?float, net_per_hour: ?float}, untagged: int}
     */
    public function compareTitleTags(?Carbon $from = null, ?Carbon $until = null, ?int $channelId = null): array
    {
        $shows = Show::query()
            ->whereNotIn('status', ['draft', 'cancelled'])
            ->when($from, fn ($q) => $q->whereDate('show_date', '>=', $from))
            ->when($until, fn ($q) => $q->whereDate('show_date', '<=', $until))
            ->when($channelId, fn ($q) => $q->where('whatnot_channel_id', $channelId))
            ->get(['id', 'title_tags', 'title_tags_version', 'gross_revenue', 'whatnot_net', 'completed_earnings', 'show_duration']);

        $summarise = function ($group): array {
            $timed = $group->filter(fn (Show $s) => (float) $s->show_duration > 0);
            $hours = $timed->sum(fn (Show $s) => (float) $s->show_duration) / 60;
            $net = fn (Show $s) => $s->completed_earnings !== null ? (float) $s->completed_earnings : (float) $s->whatnot_net;

            return [
                'shows' => $group->count(),
                'hours' => round($hours, 1),
                'gross_per_hour' => $hours > 0 ? round($timed->sum(fn (Show $s) => (float) $s->gross_revenue) / $hours, 2) : null,
                'net_per_hour' => $hours > 0 ? round($timed->sum($net) / $hours, 2) : null,
            ];
        };

        $overall = $summarise($shows);
        $tagged = $shows->filter(fn (Show $s) => $s->title_tags_version !== null);
        $rows = [];

        foreach (\App\Support\ShowTitleTags::labels() as $key => $label) {
            $group = $tagged->filter(fn (Show $s) => in_array($key, (array) $s->title_tags, true));
            if ($group->isNotEmpty()) {
                $rows[] = ['tag' => $key, 'label' => $label] + $summarise($group);
            }
        }
        $none = $tagged->filter(fn (Show $s) => empty($s->title_tags));
        if ($none->isNotEmpty()) {
            $rows[] = ['tag' => null, 'label' => 'No tags in title'] + $summarise($none);
        }

        foreach ($rows as &$row) {
            $row['gross_vs_overall_pct'] = $row['gross_per_hour'] !== null && ($overall['gross_per_hour'] ?? 0) > 0
                ? round(($row['gross_per_hour'] - $overall['gross_per_hour']) / $overall['gross_per_hour'] * 100, 1)
                : null;
        }
        unset($row);
        usort($rows, fn ($a, $b) => ($b['gross_per_hour'] ?? -1) <=> ($a['gross_per_hour'] ?? -1));

        return ['rows' => $rows, 'overall' => $overall, 'untagged' => $shows->count() - $tagged->count()];
    }
}
