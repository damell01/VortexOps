<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HasModuleAccess;
use App\Models\Show;
use App\Models\Streamer;
use App\Models\WhatnotChannel;
use App\Services\ProfitLossReport;
use App\Support\AdminModules;
use App\Support\BusinessTime;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;

/**
 * Side by side: two to four streamers, channels, shows, formats or periods.
 *
 * Every figure comes from ProfitLossReport rows (one per aired show), the
 * same numbers Business Profitability and Show Performance use, so a
 * comparison never disagrees with the report it was started from.
 */
class ReportCompare extends Page
{
    use HasModuleAccess;

    protected static string $moduleSlug = 'reporting';
    protected static ?string $title = 'Compare';
    protected static ?string $slug = 'reports/compare';
    protected static bool $shouldRegisterNavigation = false;

    public const KINDS = ['streamer' => 'Streamers', 'channel' => 'Channels', 'show' => 'Shows', 'format' => 'Formats', 'period' => 'Periods'];
    public const RANGES = ['7' => '7D', '30' => '30D', '90' => '90D', '365' => '12M'];
    public const METRICS = [
        'net' => ['Whatnot net', true, true], 'sales' => ['Gross sales', true, true], 'profit' => ['Profit', true, true],
        'margin' => ['Profit margin', false, true], 'shows' => ['Shows', false, true], 'hours' => ['Hours streamed', false, true],
        'net_hour' => ['Net per hour', true, true], 'net_show' => ['Net per show', true, true],
        'buyers' => ['Buyers', false, true], 'units' => ['Units sold', false, true],
        'cogs' => ['Cost of goods', true, false], 'pay' => ['Streamer pay', true, false],
    ];
    public const CHART_METRICS = ['net' => 'Whatnot net', 'sales' => 'Gross sales', 'profit' => 'Profit', 'shows' => 'Shows'];

    #[Url]
    public string $kind = 'streamer';

    /** Comma-separated ids (streamer/channel/show id, format key or period key). */
    #[Url]
    public string $items = '';

    #[Url]
    public string $range = '30';

    #[Url]
    public string $metric = 'net';

    #[Url]
    public bool $running = true;

    /** @var array<string, Collection> */
    private array $rowsMemo = [];

    protected static function passesModuleAccessCheck(): bool
    {
        return (bool) (auth()->user()?->isAdmin() || auth()->user()?->isOwner());
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return AdminModules::navigationGroupFor('reporting');
    }

    public function getView(): string { return 'filament.pages.report-compare'; }

    public function getHeading(): string { return ''; }

    public function mount(): void
    {
        $this->sanitise();
    }

    private function sanitise(): void
    {
        $this->kind = array_key_exists($this->kind, self::KINDS) ? $this->kind : 'streamer';
        $this->range = array_key_exists($this->range, self::RANGES) ? $this->range : '30';
        $this->metric = array_key_exists($this->metric, self::CHART_METRICS) ? $this->metric : 'net';
        $valid = array_keys($this->options());
        $ids = collect(explode(',', $this->items))->map(fn ($v) => trim((string) $v))->filter(fn ($v) => $v !== '' && in_array($v, array_map('strval', $valid), true))
            ->unique()->take(4)->values();
        if ($ids->count() < 2) {
            $ids = $ids->merge($this->defaults())->unique()->take(max(2, $ids->count()))->values();
        }
        $this->items = $ids->implode(',');
    }

    public function updated(string $name): void
    {
        if ($name === 'kind') $this->items = '';
        $this->sanitise();
    }

    public function setKind(string $kind): void { $this->kind = $kind; $this->items = ''; $this->sanitise(); }
    public function setRange(string $range): void { $this->range = $range; $this->sanitise(); }
    public function setMetric(string $metric): void { $this->metric = $metric; $this->sanitise(); }
    public function setRunning(bool $on): void { $this->running = $on; }

    public function setItem(int $slot, string $id): void
    {
        $ids = $this->ids();
        if ($slot < 0 || $slot >= count($ids)) return;
        $ids[$slot] = $id;
        $this->items = implode(',', $ids);
        $this->sanitise();
    }

    public function addItem(): void
    {
        $ids = $this->ids();
        if (count($ids) >= 4) return;
        $next = collect(array_keys($this->options()))->map(fn ($v) => (string) $v)->first(fn ($v) => ! in_array($v, $ids, true));
        if ($next !== null) $ids[] = $next;
        $this->items = implode(',', $ids);
    }

    public function removeItem(int $slot): void
    {
        $ids = $this->ids();
        if (count($ids) <= 2) return;
        array_splice($ids, $slot, 1);
        $this->items = implode(',', $ids);
    }

    public function swap(): void
    {
        $this->items = implode(',', array_reverse($this->ids()));
    }

    /** @return list<string> */
    public function ids(): array
    {
        return array_values(array_filter(explode(',', $this->items), fn ($v) => $v !== ''));
    }

    // ── Data ────────────────────────────────────────────────────────────────

    private function today(): Carbon { return BusinessTime::local()->startOfDay(); }

    /** @return array{0: Carbon, 1: Carbon} */
    public function rangeDates(): array
    {
        $end = $this->today();
        return [$end->copy()->subDays((int) $this->range - 1), $end];
    }

    private function rows(Carbon $from, Carbon $to): Collection
    {
        $key = $from->toDateString().'_'.$to->toDateString();
        return $this->rowsMemo[$key] ??= app(ProfitLossReport::class)->rows($from, $to);
    }

    /** @return array<string, array{n: string, a: Carbon, b: Carbon}> */
    public function periods(): array
    {
        $t = $this->today();
        $qStart = $t->copy()->firstOfQuarter();
        return [
            'l7' => ['n' => 'Last 7 days', 'a' => $t->copy()->subDays(6), 'b' => $t->copy()],
            'p7' => ['n' => 'Previous 7 days', 'a' => $t->copy()->subDays(13), 'b' => $t->copy()->subDays(7)],
            'l30' => ['n' => 'Last 30 days', 'a' => $t->copy()->subDays(29), 'b' => $t->copy()],
            'p30' => ['n' => 'Previous 30 days', 'a' => $t->copy()->subDays(59), 'b' => $t->copy()->subDays(30)],
            'tm' => ['n' => 'This month', 'a' => $t->copy()->startOfMonth(), 'b' => $t->copy()],
            'lm' => ['n' => 'Last month', 'a' => $t->copy()->subMonthNoOverflow()->startOfMonth(), 'b' => $t->copy()->subMonthNoOverflow()->endOfMonth()->startOfDay()],
            'tq' => ['n' => 'This quarter', 'a' => $qStart->copy(), 'b' => $t->copy()],
            'lq' => ['n' => 'Last quarter', 'a' => $qStart->copy()->subQuarterNoOverflow(), 'b' => $qStart->copy()->subDay()],
            'ly' => ['n' => 'Same 30 days last year', 'a' => $t->copy()->subYear()->subDays(29), 'b' => $t->copy()->subYear()],
            'ty' => ['n' => 'This year so far', 'a' => $t->copy()->startOfYear(), 'b' => $t->copy()],
            'lyy' => ['n' => 'Last year, same dates', 'a' => $t->copy()->subYear()->startOfYear(), 'b' => $t->copy()->subYear()],
        ];
    }

    /** id => label for the pickers. */
    public function options(): array
    {
        return match ($this->kind) {
            'streamer' => Streamer::query()->orderBy('name')->pluck('name', 'id')->mapWithKeys(fn ($n, $id) => [(string) $id => $n])->all(),
            'channel' => WhatnotChannel::query()->orderBy('name')->pluck('name', 'id')->mapWithKeys(fn ($n, $id) => [(string) $id => $n])->all(),
            'format' => collect(Show::formatLabels())->mapWithKeys(fn ($n, $k) => [(string) $k => $n])->put('none', 'Unclassified')->all(),
            'period' => collect($this->periods())->map(fn ($p) => $p['n'])->all(),
            'show' => $this->showOptions(),
        };
    }

    private function showOptions(): array
    {
        [$a, $b] = $this->rangeDates();
        $rows = $this->rows($a->copy()->subDays(max(0, 120 - (int) $this->range)), $b);
        $out = $rows->sortByDesc(fn ($r) => $r['date']?->timestamp ?? 0)->take(200)
            ->mapWithKeys(fn ($r) => [(string) $r['id'] => $r['title'].' · '.$r['date']?->format('M j').' · '.$r['streamer']])->all();
        // Keep a show picked from elsewhere selectable even if it is older.
        foreach ($this->ids() as $id) {
            if (! isset($out[$id]) && ($show = Show::query()->find((int) $id))) {
                $out[$id] = ($show->title ?: 'Show #'.$show->id).' · '.$show->show_date?->format('M j, Y');
            }
        }

        return $out;
    }

    /** @return list<string> */
    private function defaults(): array
    {
        if ($this->kind === 'period') return ['l30', 'p30'];
        [$a, $b] = $this->rangeDates();
        $rows = $this->rows($a, $b);
        $by = match ($this->kind) {
            'streamer' => $rows->filter(fn ($r) => $r['streamer_id'])->groupBy('streamer_id'),
            'channel' => $rows->filter(fn ($r) => $r['channel_id'])->groupBy('channel_id'),
            'format' => $rows->groupBy(fn ($r) => $r['format'] ?: 'none'),
            'show' => $rows->keyBy('id')->map(fn ($r) => collect([$r])),
        };
        $top = $by->map(fn ($g) => $g->sum('whatnot_net'))->sortDesc()->keys()->map(fn ($k) => (string) $k)->take(2)->all();
        if (count($top) < 2) {
            $top = array_values(array_unique(array_merge($top, array_slice(array_map('strval', array_keys($this->options())), 0, 2))));
        }

        return array_slice($top, 0, 2);
    }

    /** The shows behind one picked item. */
    private function itemRows(string $id): Collection
    {
        if ($this->kind === 'period') {
            $p = $this->periods()[$id] ?? null;
            return $p ? $this->rows($p['a'], $p['b']) : collect();
        }
        if ($this->kind === 'show') {
            $show = Show::query()->find((int) $id);
            if (! $show?->show_date) return collect();
            $day = $show->show_date->copy()->startOfDay();
            return $this->rows($day, $day)->where('id', (int) $id)->values();
        }
        [$a, $b] = $this->rangeDates();
        $rows = $this->rows($a, $b);

        return match ($this->kind) {
            // Credited to the primary streamer, as on every other report.
            'streamer' => $rows->filter(fn ($r) => (string) $r['streamer_id'] === $id)->values(),
            'channel' => $rows->filter(fn ($r) => (string) $r['channel_id'] === $id)->values(),
            'format' => $rows->filter(fn ($r) => ($r['format'] ?: 'none') === $id)->values(),
            default => collect(),
        };
    }

    /** @return array<string, float|int|null> */
    public static function figures(Collection $rows): array
    {
        $hours = (float) $rows->sum(fn ($r) => $r['hours'] ?? $r['duration_hours'] ?? 0);
        $net = (float) $rows->sum('whatnot_net');
        $earnings = (float) $rows->sum('earnings');
        $profit = (float) $rows->sum('profit');
        $shows = $rows->count();

        return [
            'net' => round($net, 2), 'sales' => round((float) $rows->sum('sales'), 2), 'profit' => round($profit, 2),
            'margin' => $earnings > 0 ? round($profit / $earnings * 100, 1) : null,
            'shows' => $shows, 'hours' => round($hours, 1),
            'net_hour' => $hours > 0 ? round($net / $hours, 2) : null,
            'net_show' => $shows > 0 ? round($net / $shows, 2) : null,
            'buyers' => (int) $rows->sum('buyers'), 'units' => (int) $rows->sum('units'),
            'cogs' => round((float) $rows->sum('cogs'), 2), 'pay' => round((float) $rows->sum('payouts'), 2),
            'tips' => round((float) $rows->sum('tips'), 2), 'fees' => round(max(0, (float) $rows->sum('sales') - $net), 2),
        ];
    }

    /** Everything the page draws. */
    public function comparison(): array
    {
        $opts = $this->options();
        $periods = $this->periods();
        $items = [];
        foreach ($this->ids() as $i => $id) {
            $rows = $this->itemRows($id);
            $f = self::figures($rows);
            $sub = match ($this->kind) {
                'period' => isset($periods[$id]) ? $periods[$id]['a']->format('M j').' – '.$periods[$id]['b']->format('M j, Y').' · '.$f['shows'].' shows' : '',
                'show' => ($r = $rows->first()) ? $r['date']?->format('D, M j').' · '.$r['streamer'].' · '.$r['channel'] : 'No results for this show yet',
                default => $f['shows'].' shows · '.number_format($f['hours'], 1).' h · last '.self::RANGES[$this->range],
            };
            $name = $this->kind === 'show' ? (($r = $rows->first()) ? $r['title'] : ($opts[$id] ?? 'Show')) : ($opts[$id] ?? $id);
            $items[] = ['id' => $id, 'i' => $i, 'name' => $name, 'sub' => $sub, 'f' => $f, 'rows' => $rows];
        }

        $best = [];
        foreach (self::METRICS as $k => [, , $higher]) {
            $vals = array_map(fn ($it) => $it['f'][$k], $items);
            $real = array_filter($vals, fn ($v) => $v !== null);
            if (count($real) < 2) { $best[$k] = null; continue; }
            $target = $higher ? max($real) : min($real);
            $best[$k] = count(array_keys($vals, $target, false)) === 1 ? array_search($target, $vals, false) : null;
        }

        return ['items' => $items, 'best' => $best, 'verdict' => $this->verdict($items), 'race' => $this->race($items)];
    }

    /** @return array{0: string, 1: string} */
    private function verdict(array $items): array
    {
        $sorted = collect($items)->sortByDesc(fn ($it) => $it['f']['net'])->values();
        [$a, $b] = [$sorted[0], $sorted[1]];
        $money = fn ($v) => ($v < 0 ? '−$' : '$').number_format(abs((float) $v), 0);
        if (! $a['f']['shows'] && ! $b['f']['shows']) return ['Nothing to compare yet', 'Neither has an aired show in this range. Try a longer range.'];
        $head = (float) $b['f']['net'] > 0
            ? sprintf('%s earned %s%% more Whatnot net than %s (%s vs %s).', $a['name'], number_format(($a['f']['net'] - $b['f']['net']) / abs($b['f']['net']) * 100, 0), $b['name'], $money($a['f']['net']), $money($b['f']['net']))
            : sprintf('%s leads with %s Whatnot net.', $a['name'], $money($a['f']['net']));
        if ($this->kind === 'show') {
            return [$head, sprintf('%s/hr vs %s/hr, %d buyers vs %d. Profit %s vs %s.', $money($a['f']['net_hour'] ?? 0), $money($b['f']['net_hour'] ?? 0), $a['f']['buyers'], $b['f']['buyers'], $money($a['f']['profit']), $money($b['f']['profit']))];
        }
        $moreShows = $a['f']['shows'] - $b['f']['shows'];
        $ra = (float) ($a['f']['net_hour'] ?? 0); $rb = (float) ($b['f']['net_hour'] ?? 0);
        $rate = $rb > 0 ? ($ra - $rb) / $rb : 0;
        $marg = fn ($it) => $it['f']['margin'] === null ? '—' : $it['f']['margin'].'%';
        $why = match (true) {
            $ra <= 0 || $rb <= 0 => sprintf('%d shows vs %d. Margin %s vs %s.', $a['f']['shows'], $b['f']['shows'], $marg($a), $marg($b)),
            $moreShows > 0 && $rate <= 0.05 => sprintf('Mostly volume: %d more shows at a similar rate (%s/hr vs %s/hr).', $moreShows, $money($ra), $money($rb)),
            $moreShows <= 0 => sprintf('Mostly efficiency: %s/hr vs %s/hr%s. Margin %s vs %s.', $money($ra), $money($rb), $moreShows < 0 ? ', even with '.abs($moreShows).' fewer shows' : '', $marg($a), $marg($b)),
            default => sprintf('Both: %d more shows and %d%% more per hour. Margin %s vs %s.', $moreShows, round($rate * 100), $marg($a), $marg($b)),
        };

        return [$head, $why];
    }

    /** Lines for the race chart: one per item, lined up by day (or week). */
    private function race(array $items): array
    {
        if ($this->kind === 'show') return [];
        $field = ['net' => 'whatnot_net', 'sales' => 'sales', 'profit' => 'profit', 'shows' => null][$this->metric];
        if ($this->kind === 'period') {
            $periods = $this->periods();
            $len = max(array_map(fn ($it) => isset($periods[$it['id']]) ? (int) $periods[$it['id']]['a']->diffInDays($periods[$it['id']]['b']) + 1 : 1, $items));
            $bucket = $len > 45 ? 7 : 1;
            $n = (int) ceil($len / $bucket);
            $labels = array_map(fn ($i) => ($bucket === 7 ? 'Wk ' : 'Day ').($i + 1), range(0, $n - 1));
            $start = fn ($it) => $periods[$it['id']]['a'] ?? $this->today();
        } else {
            [$a, $b] = $this->rangeDates();
            $len = (int) $a->diffInDays($b) + 1;
            $bucket = $len > 45 ? 7 : 1;
            $n = (int) ceil($len / $bucket);
            $labels = array_map(fn ($i) => $a->copy()->addDays($i * $bucket)->format('M j'), range(0, $n - 1));
            $start = fn () => $a;
        }
        $lines = [];
        foreach ($items as $it) {
            $vals = array_fill(0, $n, 0.0);
            $s = $start($it);
            foreach ($it['rows'] as $r) {
                if (! $r['date']) continue;
                $idx = intdiv((int) $s->copy()->startOfDay()->diffInDays($r['date']->copy()->startOfDay(), false), $bucket);
                if ($idx < 0 || $idx >= $n) continue;
                $vals[$idx] += $field ? (float) $r[$field] : 1;
            }
            if ($this->running) { $t = 0; foreach ($vals as $k => $v) { $t += $v; $vals[$k] = round($t, 2); } }
            $lines[] = array_map(fn ($v) => round($v, 2), $vals);
        }

        return ['labels' => $labels, 'lines' => $lines, 'money' => $field !== null];
    }
}
