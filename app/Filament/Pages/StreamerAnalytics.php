<?php

namespace App\Filament\Pages;

use App\Models\Payout;
use App\Models\Show;
use App\Models\Streamer;
use App\Models\StreamerLogEntry;
use App\Support\AdminModules;
use App\Support\ChannelContext;
use App\Support\NavVisibility;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Livewire\Attributes\Url;
use App\Filament\Concerns\HasAdminNavVisibility;

class StreamerAnalytics extends Page
{
    use HasAdminNavVisibility;

    protected static ?string $title = 'Streamer Analytics';

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return 'Reports';
    }

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-chart-bar';
    }

    public static function getNavigationSort(): ?int
    {
        return 10;
    }

    public static function canAccess(): bool
    {
        // An explicit grant on Roles & Permissions is the answer; the rules
        // below are the fallback for roles that have no explicit list.
        if (\App\Support\RoleAccess::grants(static::class)) {
            return true;
        }

        $user = auth()->user();

        return AdminModules::isEnabled('reporting')
            && ! NavVisibility::isHiddenForUser(static::class, $user)
            && (bool) $user?->isAdmin();
    }


    public function getView(): string
    {
        return 'filament.pages.streamer-analytics';
    }

    /**
     * The page draws its own header (title, subtitle, filters and actions in
     * one row, like every other report), so Filament's is switched off.
     */
    public function getHeading(): string
    {
        return '';
    }

    public function getSubheading(): ?string
    {
        return null;
    }

    public function subtitle(): string
    {
        return $this->restrictedToOwnFigures()
            ? 'Your show volume, earnings, efficiency and payout activity, over a date range you pick.'
            : 'Compare show volume, earnings, efficiency and payout activity.';
    }

    public function statementsUrl(): ?string
    {
        if (! StreamerStatement::canAccess()) {
            return null;
        }

        return StreamerStatement::getUrl(
            $this->selectedStreamers && count($this->selectedStreamers) === 1
                ? ['streamer' => $this->selectedStreamers[0]]
                : [],
        );
    }

    /**
     * Whose numbers this person may see.
     *
     * The page can be granted to a streamer on Roles & Permissions, and until
     * this existed that grant handed them the whole team's revenue, margin and
     * payout — a comparison screen is only useful with everyone on it, which is
     * exactly why it must not open by default.
     *
     * Admins and the owner see everyone. Anyone else sees themselves, and if
     * they are not a streamer at all they see nothing rather than everything.
     */
    private function restrictedToOwnFigures(): bool
    {
        $user = auth()->user();

        return ! ($user?->isAdmin() || $user?->isOwner());
    }

    /**
     * The streamer ids this person may see, or null for "no restriction".
     *
     * An empty array is a real answer — a non-streamer with the page granted —
     * and has to be told apart from null, or "see nothing" becomes "see all".
     *
     * @return array<int>|null
     */
    private function visibleStreamerIds(): ?array
    {
        if (! $this->restrictedToOwnFigures()) {
            return null;
        }

        $own = auth()->user()?->streamer?->id;

        return $own ? [$own] : [];
    }

    public string $dateFrom = '';
    public string $dateTo   = '';
    public string $activeTab = 'overview';

    #[Url(as: 'streamer')]
    public ?int $streamer = null;

    /** @var array<int> */
    public array $selectedStreamers = [];

    /** Streamer card grid: search, sort and page. */
    public string $cardSearch = '';
    public string $cardSort = 'net';
    public int $cardPage = 1;

    /** Top streamers bars: net | gross | sph. */
    public string $topMetric = 'net';

    /** The every-figure table opens on request, below the cards. */
    public bool $showComparison = false;

    public const CARDS_PER_PAGE = 6;

    /** Points on each card's sparkline, spread evenly across the range. */
    public const SPARK_POINTS = 12;

    /** @var array<string, string> sort key => label */
    public const CARD_SORTS = [
        'net'    => 'Net earnings',
        'gross'  => 'Gross sales',
        'shows'  => 'Shows',
        'hours'  => 'Show hours',
        'sph'    => 'Sales / hour',
        'payout' => 'Recorded payouts',
        'name'   => 'Name',
    ];

    /** @var array<string, string> */
    public const TOP_METRICS = ['gross' => 'Gross', 'net' => 'Net', 'sph' => 'Per hour'];

    /** @var array<string, string> */
    public const RANGES = ['month' => 'This month', '30' => 'Last 30 days', '90' => 'Last 90 days', 'year' => 'This year', 'last_year' => 'Last year'];

    public function mount(): void
    {
        $this->dateFrom = now()->startOfYear()->toDateString();
        $this->dateTo   = now()->toDateString();

        if ($this->streamer) {
            $this->selectedStreamers = [$this->streamer];
        }
    }

    public function refreshData(): void {}

    public function updatedCardSearch(): void
    {
        $this->cardPage = 1;
    }

    public function updatedCardSort(): void
    {
        $this->cardPage = 1;
    }

    public function updatedSelectedStreamers(): void
    {
        $this->cardPage = 1;
    }

    public function updatedDateFrom(): void
    {
        $this->cardPage = 1;
    }

    public function updatedDateTo(): void
    {
        $this->cardPage = 1;
    }

    public function setRange(string $preset): void
    {
        $today = now();
        [$from, $to] = match ($preset) {
            'month' => [$today->copy()->startOfMonth(), $today],
            '30' => [$today->copy()->subDays(29), $today],
            '90' => [$today->copy()->subDays(89), $today],
            'last_year' => [$today->copy()->subYear()->startOfYear(), $today->copy()->subYear()->endOfYear()],
            default => [$today->copy()->startOfYear(), $today],
        };
        $this->dateFrom = $from->toDateString();
        $this->dateTo = $to->toDateString();
        $this->cardPage = 1;
    }

    /** "Oct 1 – 21, 2026", "Sep 3 – Oct 21, 2026" or across years in full. */
    public function rangeLabel(): string
    {
        try {
            $a = Carbon::parse($this->dateFrom ?: now()->startOfYear());
            $b = Carbon::parse($this->dateTo ?: now());
        } catch (\Throwable) {
            return 'Pick dates';
        }
        if ($a->year !== $b->year) return $a->format('M j, Y').' – '.$b->format('M j, Y');
        if ($a->month === $b->month) return $a->format('M j').' – '.$b->format('j, Y');

        return $a->format('M j').' – '.$b->format('M j, Y');
    }

    public function updatedTopMetric(): void
    {
        if (! array_key_exists($this->topMetric, self::TOP_METRICS)) $this->topMetric = 'net';
    }

    /** "View analytics" on a card: the whole page narrows to that streamer. */
    public function focusStreamer(int $id): void
    {
        $this->selectedStreamers = [$id];
        $this->cardPage = 1;
    }

    public function clearSelectedStreamers(): void
    {
        $this->selectedStreamers = [];
        $this->cardPage = 1;
    }

    public function goToCardPage(int $page): void
    {
        $this->cardPage = max(1, $page);
    }

    /**
     * The streamer cards after search and sort, cut to the current page.
     *
     * @return array{rows: array<array<string,mixed>>, total: int, page: int, pages: int, from: int, to: int}
     */
    public function getCardGridProperty(): array
    {
        $rows = collect($this->analyticsRows);

        $field = [
            'gross'  => 'gross_revenue',
            'net'    => 'net_revenue',
            'shows'  => 'show_count',
            'hours'  => 'total_hours',
            'sph'    => 'gmv_per_hour',
            'payout' => 'total_payout',
        ][$this->cardSort] ?? null;

        $rows = $field
            ? $rows->sortBy([[$field, 'desc'], ['name', 'asc']])
            : $rows->sortBy(fn ($r) => mb_strtolower((string) $r['name']));
        // Rank before the search narrows the list, so a found card keeps its place.
        $rows = $rows->values()->map(fn ($r, $i) => $r + ['rank' => $field ? $i + 1 : null]);

        $needle = mb_strtolower(trim($this->cardSearch));
        if ($needle !== '') {
            $rows = $rows->filter(fn ($r) => str_contains(mb_strtolower((string) $r['name']), $needle));
        }

        $total = $rows->count();
        $pages = max(1, (int) ceil($total / self::CARDS_PER_PAGE));
        $page  = min(max(1, $this->cardPage), $pages);
        $slice = $rows->values()->slice(($page - 1) * self::CARDS_PER_PAGE, self::CARDS_PER_PAGE)->values()->all();

        return [
            'rows'  => $slice,
            'total' => $total,
            'page'  => $page,
            'pages' => $pages,
            'from'  => $total ? ($page - 1) * self::CARDS_PER_PAGE + 1 : 0,
            'to'    => ($page - 1) * self::CARDS_PER_PAGE + count($slice),
        ];
    }

    /** Up to five streamers with the most of the chosen figure in the range. */
    public function getTopStreamersProperty(): array
    {
        $field = ['gross' => 'gross_revenue', 'sph' => 'gmv_per_hour'][$this->topMetric] ?? 'net_revenue';

        return collect($this->analyticsRows)
            ->filter(fn ($r) => $r[$field] > 0)
            ->map(fn ($r) => $r + ['top_value' => $r[$field]])
            ->sortByDesc($field)
            ->take(5)
            ->values()
            ->all();
    }

    public function exportCsv(): StreamedResponse
    {
        $rows = $this->analyticsRows;
        $from = $this->dateFrom ?: now()->startOfYear()->toDateString();
        $to   = $this->dateTo   ?: now()->toDateString();

        $filename = 'streamer-analytics-' . $from . '-to-' . $to . '.csv';

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, [
                'Streamer', 'Payout Type', 'Shows', 'Hours', 'Gross Revenue', 'Trend vs Prior %',
                'Whatnot Net', 'Margin', 'Margin %', 'SPH', 'Tips', 'Total Payout', 'Total Paid', 'Balance',
            ]);
            foreach ($rows as $row) {
                fputcsv($out, [
                    $row['name'],
                    $row['payout_type'],
                    $row['show_count'],
                    number_format($row['total_hours'], 2),
                    number_format($row['gross_revenue'], 2),
                    $row['trend_gross'] !== null ? $row['trend_gross'] : '',
                    number_format($row['net_revenue'], 2),
                    number_format($row['margin'], 2),
                    $row['margin_pct'] !== null ? $row['margin_pct'] : '',
                    number_format($row['gmv_per_hour'], 2),
                    number_format($row['tips'], 2),
                    number_format($row['total_payout'], 2),
                    number_format($row['total_paid'], 2),
                    number_format($row['balance'], 2),
                ]);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function getStreamersListProperty(): Collection
    {
        $query = Streamer::where('status', 'active')->inChannelContext()->orderBy('name');

        if (($ids = $this->visibleStreamerIds()) !== null) {
            $query->whereIn('id', $ids);
        }

        return $query->get(['id', 'name']);
    }

    private function cacheKey(string $section): string
    {
        $streamers = $this->selectedStreamers ? implode(',', $this->selectedStreamers) : 'all';

        return "streamer_analytics_{$section}_{$this->dateFrom}_{$this->dateTo}_{$streamers}_"
            . auth()->id() . '_ch' . (ChannelContext::currentId() ?? 'all');
    }

    /**
     * @return array<array<string,mixed>>
     */
    public function getAnalyticsRowsProperty(): array
    {
        return Cache::remember($this->cacheKey('rows-v2'), 60, fn () => $this->buildAnalyticsRows());
    }

    /**
     * @return array<array<string,mixed>>
     */
    private function buildAnalyticsRows(): array
    {
        $from = $this->dateFrom ?: now()->startOfYear()->toDateString();
        $to   = $this->dateTo   ?: now()->toDateString();
        // Upper bound needs a time component: a show_date row dated "today" is
        // stored with a midnight timestamp, which a bare date-string upper
        // bound would exclude via lexical comparison on SQLite.
        $toBound = Carbon::parse($to)->endOfDay()->toDateTimeString();

        $query = Streamer::query()
            ->where('status', 'active')
            ->inChannelContext()
            ->with([
                'shows' => function ($q) use ($from, $toBound) {
                    $q->select(['shows.id', 'show_date', 'gross_revenue', 'whatnot_net', 'units_sold', 'show_duration'])
                        ->whereBetween('show_date', [$from, $toBound]);
                },
                'shows.orders:id,show_id,total_cost',
                'payouts' => function ($q) use ($from, $toBound) {
                    $q->select(['id', 'show_id', 'streamer_id', 'tips_included', 'calculated_payout', 'status'])
                        ->whereHas('show', fn ($s) => $s->whereBetween('show_date', [$from, $toBound]));
                },
                'business:id,name',
                'user:id,buddy',
                'streamerLogEntries' => function ($q) use ($from, $toBound) {
                    $q->select(['id', 'show_id', 'streamer_id', 'hours_streamed', 'gross_revenue'])
                        ->whereHas('show', fn ($s) => $s->whereBetween('show_date', [$from, $toBound]));
                },
            ]);

        if (! empty($this->selectedStreamers)) {
            $query->whereIn('id', $this->selectedStreamers);
        }

        // Applied last and unconditionally, so a hand-set selection cannot
        // widen what the filter above narrowed.
        if (($ids = $this->visibleStreamerIds()) !== null) {
            $query->whereIn('id', $ids);
        }

        $priorGrossByStreamer = $this->priorPeriodGrossByStreamer($from, $to);

        $rows = [];
        $sparkFrom = Carbon::parse($from)->startOfDay();
        $sparkSpan = max(1, $sparkFrom->diffInDays(Carbon::parse($to)->endOfDay()));

        foreach ($query->get() as $streamer) {
            $shows   = $streamer->shows;
            $payouts = $streamer->payouts;
            $logs    = $streamer->streamerLogEntries;

            $showCount    = $shows->count();
            $grossRevenue = (float) $shows->sum('gross_revenue');
            $netRevenue   = (float) $shows->sum('whatnot_net');
            $tips         = (float) $payouts->sum('tips_included');
            $unitsSold    = (int) $shows->sum('units_sold');
            $totalPayout  = (float) $payouts->sum('calculated_payout');
            $totalPaid    = (float) $payouts->where('status', 'paid')->sum('calculated_payout');
            $cogs         = (float) $shows->flatMap(fn ($s) => $s->orders)->sum('total_cost');

            // Prefer hours from StreamerLogEntry; fall back to show_duration
            $totalHours = (float) $logs->sum('hours_streamed');
            if ($totalHours == 0) {
                $totalMinutes = (int) $shows->sum('show_duration');
                $totalHours   = round($totalMinutes / 60, 2);
            }

            $grossRevenue = max($grossRevenue, (float) $logs->sum('gross_revenue'));

            $businessNet = max(0, $netRevenue - $totalPayout);
            $gmvPerHour  = $totalHours > 0 ? round($grossRevenue / $totalHours, 2) : 0;
            $aov         = $unitsSold  > 0 ? round($grossRevenue / $unitsSold,  2) : 0;
            $margin      = round($grossRevenue - $cogs, 2);

            // Outstanding balance from streamer totals
            $balance = (float) ($streamer->total_earnings_due ?? 0) - (float) ($streamer->total_earnings_paid ?? 0);

            $priorGross = (float) ($priorGrossByStreamer[$streamer->id] ?? 0);
            $trendGross = $priorGross > 0 ? round((($grossRevenue - $priorGross) / $priorGross) * 100, 1) : null;

            // Whatnot net over the range, in even slices, for the card's sparkline.
            $spark = array_fill(0, self::SPARK_POINTS, 0.0);
            foreach ($shows as $show) {
                $slot = (int) floor($sparkFrom->diffInDays($show->show_date) / $sparkSpan * self::SPARK_POINTS);
                $spark[min(self::SPARK_POINTS - 1, max(0, $slot))] += (float) $show->whatnot_net;
            }

            $rows[] = [
                'streamer_id'  => $streamer->id,
                'name'         => $streamer->name,
                'business'     => $streamer->business?->name,
                'buddy'        => self::buddyFor($streamer->user?->buddy, $streamer->id),
                'spark'        => array_map(fn ($v) => round($v, 2), $spark),
                'payout_type'  => $streamer->payout_type,
                'show_count'   => $showCount,
                'total_hours'  => $totalHours,
                'gross_revenue'=> $grossRevenue,
                'trend_gross'  => $trendGross,
                'net_revenue'  => $netRevenue,
                'tips'         => $tips,
                'gmv_per_hour' => $gmvPerHour,
                'aov'          => $aov,
                'cogs'         => $cogs,
                'margin'       => $margin,
                'margin_pct'   => $grossRevenue > 0 ? round($margin / $grossRevenue * 100, 1) : null,
                'total_payout' => $totalPayout,
                'total_paid'   => $totalPaid,
                'business_net' => $businessNet,
                'units_sold'   => $unitsSold,
                'balance'      => $balance,
            ];
        }

        usort($rows, fn ($a, $b) => $b['gmv_per_hour'] <=> $a['gmv_per_hour']);

        return $rows;
    }

    /**
     * Gross revenue per streamer for the equal-length period immediately
     * preceding $from, so the table can show a vs-prior-period trend without
     * hydrating a whole second set of models.
     *
     * @return array<int, float> streamer_id => gross
     */
    private function priorPeriodGrossByStreamer(string $from, string $to): array
    {
        $fromDate = Carbon::parse($from);
        $span     = $fromDate->diffInDays(Carbon::parse($to)) ?: 1;
        $priorTo  = $fromDate->copy()->subDay();
        $priorFrom = $priorTo->copy()->subDays($span);

        $query = DB::table('show_streamer')
            ->join('shows', 'show_streamer.show_id', '=', 'shows.id')
            ->whereBetween('shows.show_date', [$priorFrom->toDateString(), $priorTo->copy()->endOfDay()->toDateTimeString()]);

        if (($ids = $this->visibleStreamerIds()) !== null) {
            $query->whereIn('show_streamer.streamer_id', $ids);
        }

        return $query
            ->groupBy('show_streamer.streamer_id')
            ->selectRaw('show_streamer.streamer_id, COALESCE(SUM(shows.gross_revenue), 0) as gross')
            ->pluck('gross', 'streamer_id')
            ->all();
    }

    /**
     * Weekly breakdown: aggregates gross, net, hours, and SPH per week.
     * @return array<array<string,mixed>>
     */
    public function getWeeklyRowsProperty(): array
    {
        return Cache::remember($this->cacheKey('weekly'), 60, fn () => $this->buildWeeklyRows());
    }

    /**
     * @return array<array<string,mixed>>
     */
    private function buildWeeklyRows(): array
    {
        $from = $this->dateFrom ?: now()->startOfYear()->toDateString();
        $to   = $this->dateTo   ?: now()->toDateString();
        $toBound = Carbon::parse($to)->endOfDay()->toDateTimeString();

        $shows = Show::query()
            ->select(['id', 'show_date', 'gross_revenue', 'whatnot_net', 'show_duration', 'whatnot_channel_id'])
            ->whereBetween('show_date', [$from, $toBound])
            ->inChannelContext()
            ->with('streamerLogEntry:id,show_id,hours_streamed')
            // Comparing a few streamers narrows the trend and weekly table too.
            ->when(
                ! empty($this->selectedStreamers),
                fn ($q) => $q->whereHas('streamers', fn ($s) => $s->whereIn('streamers.id', $this->selectedStreamers)),
            )
            ->when(
                $this->visibleStreamerIds() !== null,
                // Without this the weekly table was every show in the business,
                // which is the whole company's gross and net rather than one
                // person's — the largest of the leaks on this page.
                fn ($q) => $q->whereHas('streamers', fn ($s) => $s->whereIn('streamers.id', $this->visibleStreamerIds())),
            )
            ->orderBy('show_date')
            ->get();

        $weeks = [];

        foreach ($shows as $show) {
            $weekKey   = $show->show_date->copy()->startOfWeek()->format('Y-m-d');
            $weekLabel = $show->show_date->copy()->startOfWeek()->format('M j');

            if (! isset($weeks[$weekKey])) {
                $weeks[$weekKey] = ['week' => $weekLabel, 'show_count' => 0, 'gross' => 0.0, 'net' => 0.0, 'hours' => 0.0];
            }

            $weeks[$weekKey]['show_count']++;
            $weeks[$weekKey]['gross'] += (float) $show->gross_revenue;
            $weeks[$weekKey]['net']   += (float) $show->whatnot_net;

            // Prefer logged hours; fall back to show_duration (minutes → hours)
            $logHours = (float) ($show->streamerLogEntry?->hours_streamed ?? 0);
            $weeks[$weekKey]['hours'] += $logHours > 0
                ? $logHours
                : round((int) ($show->show_duration ?? 0) / 60, 2);
        }

        return array_values(array_map(function (array $data) {
            return [
                'week'       => $data['week'],
                'show_count' => $data['show_count'],
                'gross'      => $data['gross'],
                'net'        => $data['net'],
                'hours'      => $data['hours'],
                'sph'        => $data['hours'] > 0 ? round($data['gross'] / $data['hours'], 2) : 0,
            ];
        }, $weeks));
    }

    public function getTopStreamerProperty(): ?array
    {
        $rows = $this->getAnalyticsRowsProperty();
        return ! empty($rows) ? $rows[0] : null;
    }

    /** The streamer's chosen buddy sprite, or a steady stand-in when they have none. */
    public static function buddyFor(?string $buddy, int $streamerId): string
    {
        return is_string($buddy) && preg_match('/^[a-z0-9-]+$/', $buddy) && is_file(public_path('img/pokemon3d/'.$buddy.'.gif'))
            ? $buddy
            : ['pikachu', 'charizard', 'blastoise', 'gengar', 'dragonite', 'eevee', 'snorlax', 'lucario'][$streamerId % 8];
    }
}
