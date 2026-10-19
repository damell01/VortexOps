<?php

namespace App\Services;

use Illuminate\Support\Collection;

/**
 * When shows do best and which shows did best, from the same rows as Business
 * Profitability (ProfitLossReport) so the figures cannot disagree with it.
 *
 * Net is business profit after product cost and pay, and is only counted for
 * a finalized show; a show still missing earnings, COGS or final pay is
 * "pending" and stays out of every net figure rather than counting as $0.
 * Gross is Whatnot sales and counts for every show.
 */
class ShowPerformanceReport
{
    public const DAYS = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

    /** Two-hour blocks by start hour: 0 = 12–2 AM … 11 = 10 PM–12 AM. */
    public const BLOCKS = 12;

    public const METRICS = [
        'net_hour' => 'Net / hour',
        'gross_hour' => 'Gross / hour',
        'shows' => 'Shows',
    ];

    public const BOARD = [
        'net' => 'Net profit',
        'gross' => 'Gross sales',
        'net_hour' => 'Net / hour',
    ];

    /** Fewest shows a time slot needs before it can be ranked as a best time. */
    public const MIN_SHOWS = 3;

    /**
     * Each P&L row with the fields this report ranks on.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function enrich(Collection $rows, array $durations = []): Collection
    {
        return $rows->map(function (array $r) use ($durations) {
            $hours = $r['hours'] ?? null;
            if (($hours === null || $hours <= 0) && isset($durations[$r['id']]) && $durations[$r['id']] > 0) {
                $hours = $durations[$r['id']] / 60;
            }
            $hours = $hours !== null && $hours > 0 ? round((float) $hours, 2) : null;
            $final = $r['incomplete'] === [];

            return $r + [
                'final' => $final,
                'stream_hours' => $hours,
                'net' => $final ? (float) $r['profit'] : null,
                'net_hour' => $final && $hours ? round($r['profit'] / $hours, 2) : null,
                'gross_hour' => $hours ? round($r['sales'] / $hours, 2) : null,
                'day' => $r['date'] ? ((int) $r['date']->format('N')) - 1 : null,
                'block' => $r['start'] ? intdiv((int) $r['start']->format('G'), 2) : null,
            ];
        })->values();
    }

    /** @return array<string, float|int|null> */
    public function totals(Collection $rows): array
    {
        $final = $rows->where('final', true);
        $finalHours = (float) $final->sum(fn ($r) => $r['stream_hours'] ?? 0);
        $finalTimed = $final->filter(fn ($r) => $r['stream_hours']);

        return [
            'shows' => $rows->count(),
            'final' => $final->count(),
            'pending' => $rows->count() - $final->count(),
            'gross' => round((float) $rows->sum('sales'), 2),
            'net' => $final->isEmpty() ? null : round((float) $final->sum('profit'), 2),
            'net_hour' => $finalHours > 0 ? round((float) $finalTimed->sum('profit') / $finalHours, 2) : null,
            'hours' => round((float) $rows->sum(fn ($r) => $r['stream_hours'] ?? 0), 1),
        ];
    }

    /**
     * The day × time grid. Each cell: shows, gross, net (finalized only) and
     * the per-hour rates, which are totals over hours rather than an average
     * of per-show rates, so a long show weighs as much as it streamed.
     *
     * @return array{cells: array<int, array<int, array>>, blocks: int[], untimed: int}
     */
    public function heatmap(Collection $rows): array
    {
        $cells = [];
        foreach (range(0, 6) as $d) {
            foreach (range(0, self::BLOCKS - 1) as $b) {
                $cells[$d][$b] = $this->cell($rows->filter(fn ($r) => $r['day'] === $d && $r['block'] === $b));
            }
        }
        $used = $rows->pluck('block')->filter(fn ($b) => $b !== null);
        $blocks = $used->isEmpty() ? range(8, 11) : range(min(8, $used->min()), max(11, $used->max()));

        return ['cells' => $cells, 'blocks' => $blocks, 'untimed' => $rows->whereNull('block')->count()];
    }

    /** @return array<string, mixed> */
    private function cell(Collection $rows): array
    {
        $timed = $rows->filter(fn ($r) => $r['stream_hours']);
        $hours = (float) $timed->sum('stream_hours');
        $final = $timed->where('final', true);
        $finalHours = (float) $final->sum('stream_hours');

        return [
            'ids' => $rows->pluck('id')->all(),
            'shows' => $rows->count(),
            'final' => $rows->where('final', true)->count(),
            'gross' => round((float) $rows->sum('sales'), 2),
            'net' => $rows->where('final', true)->isEmpty() ? null : round((float) $rows->where('final', true)->sum('profit'), 2),
            'gross_hour' => $hours > 0 ? round((float) $timed->sum('sales') / $hours, 2) : null,
            'net_hour' => $finalHours > 0 ? round((float) $final->sum('profit') / $finalHours, 2) : null,
        ];
    }

    /** The value a cell is shaded by. */
    public function cellValue(array $cell, string $metric): ?float
    {
        return match ($metric) {
            'shows' => $cell['shows'] > 0 ? (float) $cell['shows'] : null,
            'gross_hour' => $cell['gross_hour'],
            default => $cell['net_hour'],
        };
    }

    /**
     * Best day-and-time slots for a metric, ranked. Slots with fewer than
     * MIN_SHOWS shows are left out so one lucky night doesn't top the list;
     * if nothing qualifies yet, the best of what there is shows, flagged.
     *
     * @return array{rows: array<int, array>, thin: bool}
     */
    public function bestTimes(array $heatmap, string $metric, int $limit = 5): array
    {
        $all = [];
        foreach ($heatmap['cells'] as $d => $row) {
            foreach ($row as $b => $cell) {
                $value = $this->cellValue($cell, $metric);
                if ($value !== null) {
                    $all[] = ['day' => $d, 'block' => $b, 'value' => $value] + $cell;
                }
            }
        }
        usort($all, fn ($a, $b) => $b['value'] <=> $a['value'] ?: $b['shows'] <=> $a['shows']);
        $enough = array_values(array_filter($all, fn ($c) => $c['shows'] >= self::MIN_SHOWS));
        $thin = $enough === [] && $all !== [];

        return ['rows' => array_slice($thin ? $all : $enough, 0, $limit), 'thin' => $thin];
    }

    /**
     * Top shows by gross, net or net per hour. Net rankings use finalized
     * shows only; gross counts every show.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function leaderboard(Collection $rows, string $by, int $limit = 10): Collection
    {
        $key = match ($by) {
            'gross' => 'sales',
            'net_hour' => 'net_hour',
            default => 'net',
        };

        return $rows->filter(fn ($r) => $r[$key] !== null && ($key !== 'sales' || $r['sales'] > 0))
            ->sortByDesc($key)->take($limit)->values()
            ->map(fn ($r, $i) => $r + ['rank' => $i + 1, 'value' => (float) $r[$key]]);
    }

    public static function blockLabel(int $block): string
    {
        $fmt = fn (int $h) => ($h % 12 === 0 ? 12 : $h % 12).' '.($h < 12 || $h === 24 ? 'AM' : 'PM');
        $start = $block * 2;

        return $fmt($start).' – '.$fmt(($start + 2) % 24 === 0 ? 24 : $start + 2);
    }

    public static function blockShort(int $block): string
    {
        $h = $block * 2;

        return ($h % 12 === 0 ? 12 : $h % 12).($h < 12 ? 'a' : 'p');
    }

    public static function dayName(int $day): string
    {
        return ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'][$day];
    }
}
