<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HasModuleAccess;
use App\Models\Show;
use App\Support\AdminModules;
use App\Support\ChannelContext;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class Reports extends Page
{
    use HasModuleAccess;

    protected static string $moduleSlug  = 'reporting';
    protected static ?string $title = 'Whatnot Performance';

    protected static ?string $navigationLabel = 'Whatnot Performance';

    public static function getNavigationIcon(): string|\BackedEnum|null { return 'heroicon-o-chart-bar'; }
    public static function getNavigationSort(): ?int                     { return 1; }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return AdminModules::navigationGroupFor('reporting');
    }

    protected static function passesModuleAccessCheck(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public function getView(): string
    {
        return 'filament.pages.reports';
    }

    public function getHeading(): string
    {
        return ''; // the view draws its own header with the period controls
    }

    public function inventoryReportUrl(): ?string
    {
        return InventoryReport::canAccess() ? InventoryReport::getUrl() : null;
    }

    /** Central report-hub destinations. Keep report discovery here while
     * operational pages link directly to the relevant prebuilt report. */
    public function streamerAnalyticsUrl(): ?string
    {
        return StreamerAnalytics::canAccess() ? StreamerAnalytics::getUrl() : null;
    }

    public function streamerStatementUrl(): ?string
    {
        return StreamerStatement::canAccess() ? StreamerStatement::getUrl() : null;
    }

    public function financeLedgerUrl(): ?string
    {
        return \App\Filament\Resources\WhatnotLedgerResource::canAccess()
            ? \App\Filament\Resources\WhatnotLedgerResource::getUrl('index')
            : null;
    }

    public function profitLossUrl(): ?string
    {
        return ProfitLoss::canAccess() ? ProfitLoss::getUrl() : null;
    }

    public function productInsightsUrl(): ?string
    {
        return ProductInsights::canAccess() ? ProductInsights::getUrl() : null;
    }

    // ── Period ────────────────────────────────────────────────────────────────

    public string $period    = '30';
    public string $dateFrom  = '';
    public string $dateTo    = '';
    public bool $showAllWeeks = false;

    /** '' follows the channel picked in the top bar; a channel id narrows this page to it. */
    public string $channel = '';

    /** Metric drawn on the Revenue by Week chart. */
    public string $weekMetric = 'gross';

    /** How Top Streamers is ranked. */
    public string $streamerSort = 'gross';

    public const WEEK_METRICS = ['gross' => 'Gross Revenue', 'net' => 'Est. Earnings', 'units' => 'Units Sold', 'shows' => 'Shows'];

    public const STREAMER_SORTS = ['gross' => 'By Gross Revenue', 'net' => 'By Est. Earnings', 'shows' => 'By Shows'];

    /** The channel this page reports on: its own picker first, then the top-bar context. */
    public function channelId(): ?int
    {
        if ($this->channel !== '' && ctype_digit($this->channel)) {
            return (int) $this->channel;
        }

        return ChannelContext::currentId();
    }

    public function channelOptions(): array
    {
        return ChannelContext::available()->pluck('name', 'id')->all();
    }

    public function updatedWeekMetric(string $value): void
    {
        if (! array_key_exists($value, self::WEEK_METRICS)) {
            $this->weekMetric = 'gross';
        }
    }

    public function updatedStreamerSort(string $value): void
    {
        if (! array_key_exists($value, self::STREAMER_SORTS)) {
            $this->streamerSort = 'gross';
        }
    }

    public function updatedChannel(string $value): void
    {
        if ($value !== '' && ! array_key_exists((int) $value, $this->channelOptions())) {
            $this->channel = '';
        }
    }

    /** "vs previous 30 days" — what the stat-card deltas are measured against. */
    public function trendLabel(): string
    {
        return in_array($this->period, ['7', '30', '90'], true)
            ? "vs previous {$this->period} days"
            : 'vs previous period';
    }

    /** Other reports this page links to, skipping any the viewer cannot open. */
    public function jumpLinks(): array
    {
        $links = [
            [fn () => $this->inventoryReportUrl(), 'Inventory Reports', 'Stock value, movement and aging', 'heroicon-o-cube'],
            [fn () => $this->streamerAnalyticsUrl(), 'Streamer Analytics', 'Per-streamer performance and trends', 'heroicon-o-user-group'],
            [fn () => $this->streamerStatementUrl(), 'Streamer Statements', 'Pay statements per streamer', 'heroicon-o-document-text'],
            [fn () => $this->financeLedgerUrl(), 'Whatnot Ledger', 'Transaction details and reconciliation', 'heroicon-o-currency-dollar'],
            [fn () => $this->profitLossUrl(), 'Profit & Loss', 'Profit after costs and pay runs', 'heroicon-o-scale'],
            [fn () => $this->productInsightsUrl(), 'Product Insights', 'Best sellers, margins and inventory performance', 'heroicon-o-chart-bar-square'],
        ];

        $out = [];
        foreach ($links as [$url, $label, $text, $icon]) {
            if ($href = $url()) {
                $out[] = ['label' => $label, 'text' => $text, 'url' => $href, 'icon' => $icon];
            }
        }

        return $out;
    }

    /** What the selected range is compared with: previous | last_year | custom. */
    public string $compareMode = 'previous';
    public string $compareFrom = '';
    public string $compareTo   = '';

    public function mount(): void
    {
        $this->dateFrom = now()->subDays(30)->toDateString();
        $this->dateTo   = now()->toDateString();
    }

    public function getPeriodOptions(): array
    {
        return [
            '7'   => 'Last 7 days',
            '30'  => 'Last 30 days',
            '90'  => 'Last 90 days',
            '365' => 'This year',
        ];
    }

    public function setPeriod(string $days): void
    {
        abort_unless(array_key_exists($days, $this->getPeriodOptions()), 422);
        $this->period    = $days;
        // "This year" means since January 1st, not the last 365 days.
        $this->dateFrom  = ($days === '365' ? now()->startOfYear() : now()->subDays((int) $days))->toDateString();
        $this->dateTo    = now()->toDateString();
        $this->showAllWeeks = false;
    }

    public function applyCustomRange(): void
    {
        $this->validate([
            'dateFrom' => ['required', 'date_format:Y-m-d'],
            'dateTo' => ['required', 'date_format:Y-m-d', 'after_or_equal:dateFrom'],
        ]);
        $this->period = 'custom';
        $this->showAllWeeks = false;
    }

    public function toggleAllWeeks(): void
    {
        $this->showAllWeeks = ! $this->showAllWeeks;
    }

    // ── Cache key ────────────────────────────────────────────────────────────

    private function cacheKey(string $section): string
    {
        $key = $this->period === 'custom' ? "{$this->dateFrom}_{$this->dateTo}" : $this->period;
        return "reports_{$section}_{$key}_" . auth()->id() . '_ch' . ($this->channelId() ?? 'all');
    }

    private function validDate(string $date): bool
    {
        if (! preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $parts)) return false;
        return checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1]);
    }

    private function periodStart(): Carbon
    {
        if ($this->period === 'custom' && $this->validDate($this->dateFrom)) {
            return Carbon::parse($this->dateFrom)->startOfDay();
        }
        if ($this->period === '365') {
            return now()->startOfYear();
        }
        return now()->subDays($this->period === 'custom' ? 30 : (int) $this->period)->startOfDay();
    }

    private function periodEnd(): Carbon
    {
        if ($this->period === 'custom' && $this->validDate($this->dateTo)) {
            return Carbon::parse($this->dateTo)->endOfDay();
        }
        return now()->endOfDay();
    }

    // ── CSV Export ────────────────────────────────────────────────────────────

    public function exportCsv(): StreamedResponse
    {
        $start = $this->periodStart();
        $end   = $this->periodEnd();

        $shows = Show::query()
            ->with(['whatnotChannel:id,name', 'streamers:id,name'])
            ->whereBetween('show_date', [$start, $end])
            ->whereNotIn('status', ['cancelled'])
            ->when($this->channelId(), fn ($q, $id) => $q->where('whatnot_channel_id', $id))
            ->orderBy('show_date')
            ->get(['id', 'title', 'show_date', 'gross_revenue', 'whatnot_net', 'tips', 'units_sold', 'status', 'whatnot_channel_id']);

        $filename = 'shows-report-' . $start->format('Y-m-d') . '-to-' . $end->format('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($shows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Date', 'Title', 'Channel', 'Streamers', 'Status', 'Gross Revenue', 'Estimated Net Earnings', 'Tips', 'Units Sold']);
            foreach ($shows as $show) {
                fputcsv($out, [
                    $show->show_date?->format('Y-m-d'),
                    $show->title,
                    $show->whatnotChannel?->name ?? '',
                    $show->streamers->pluck('name')->join(', '),
                    $show->status,
                    number_format((float) $show->gross_revenue, 2),
                    number_format((float) $show->whatnot_net, 2),
                    number_format((float) $show->tips, 2),
                    $show->units_sold,
                ]);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    // ── Revenue summary ───────────────────────────────────────────────────────

    public function getRevenueSummaryProperty(): array
    {
        return Cache::remember($this->cacheKey('summary'), 300, function () {
            $start = $this->periodStart();
            $end   = $this->periodEnd();
            $span  = $start->diffInDays($end) ?: 1;

            $cur = DB::table('shows')
                ->whereBetween('show_date', [$start, $end])
                ->whereNotIn('status', ['cancelled'])
                ->when($this->channelId() !== null, fn ($q) => $q->where('whatnot_channel_id', $this->channelId()))
                ->selectRaw('
                    COUNT(*) as shows,
                    COALESCE(SUM(gross_revenue), 0) as gross,
                    COALESCE(SUM(whatnot_net), 0) as net,
                    COALESCE(SUM(tips), 0) as tips,
                    COALESCE(SUM(paper_sales_gross), 0) as paper,
                    COALESCE(SUM(units_sold), 0) as units
                ')
                ->first();

            $prev = DB::table('shows')
                ->whereBetween('show_date', [
                    $start->copy()->subDays($span),
                    $start->copy()->subDay(),
                ])
                ->whereNotIn('status', ['cancelled'])
                ->when($this->channelId() !== null, fn ($q) => $q->where('whatnot_channel_id', $this->channelId()))
                ->selectRaw('
                    COUNT(*) as shows,
                    COALESCE(SUM(gross_revenue), 0) as gross,
                    COALESCE(SUM(whatnot_net), 0) as net,
                    COALESCE(SUM(units_sold), 0) as units
                ')
                ->first();

            $cogs = (float) DB::table('whatnot_show_orders')
                ->join('shows', 'whatnot_show_orders.show_id', '=', 'shows.id')
                ->whereBetween('shows.show_date', [$start, $end])
                ->whereNotIn('shows.status', ['cancelled'])
                ->when($this->channelId() !== null, fn ($q) => $q->where('shows.whatnot_channel_id', $this->channelId()))
                ->sum('whatnot_show_orders.total_cost');

            $prevCogs = (float) DB::table('whatnot_show_orders')
                ->join('shows', 'whatnot_show_orders.show_id', '=', 'shows.id')
                ->whereBetween('shows.show_date', [$start->copy()->subDays($span), $start->copy()->subDay()])
                ->whereNotIn('shows.status', ['cancelled'])
                ->when($this->channelId() !== null, fn ($q) => $q->where('shows.whatnot_channel_id', $this->channelId()))
                ->sum('whatnot_show_orders.total_cost');

            $trend = fn ($cur, $prev) => $prev > 0 ? round((($cur - $prev) / $prev) * 100, 1) : null;

            $gross  = (float) ($cur->gross ?? 0);
            $margin = round($gross - $cogs, 2);
            $prevMargin = round((float) ($prev->gross ?? 0) - $prevCogs, 2);

            return [
                'gross'        => $gross,
                'net'          => (float) ($cur->net ?? 0),
                'tips'         => (float) ($cur->tips ?? 0),
                'paper'        => (float) ($cur->paper ?? 0),
                'shows'        => (int) ($cur->shows ?? 0),
                'units'        => (int) ($cur->units ?? 0),
                'cogs'         => round($cogs, 2),
                'margin'       => $margin,
                'margin_pct'   => $gross > 0 ? round($margin / $gross * 100, 1) : null,
                'trend_gross'  => $trend($cur->gross ?? 0, $prev->gross ?? 0),
                'trend_net'    => $trend($cur->net ?? 0, $prev->net ?? 0),
                'trend_units'  => $trend($cur->units ?? 0, $prev->units ?? 0),
                'trend_shows'  => $trend($cur->shows ?? 0, $prev->shows ?? 0),
                'trend_margin' => $trend($margin, $prevMargin),
            ];
        });
    }

    // ── Compare two periods ───────────────────────────────────────────────────

    public function compareOptions(): array
    {
        return ['previous' => 'Previous period', 'last_year' => 'Same period last year', 'custom' => 'Custom range'];
    }

    public function updatedCompareMode(string $mode): void
    {
        abort_unless(array_key_exists($mode, $this->compareOptions()), 422);
        if ($mode === 'custom' && ($this->compareFrom === '' || $this->compareTo === '')) {
            [$from, $to] = $this->comparisonRange('previous');
            $this->compareFrom = $from->toDateString();
            $this->compareTo = $to->toDateString();
        }
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private function comparisonRange(?string $mode = null): array
    {
        $start = $this->periodStart();
        $end = $this->periodEnd();

        return match ($mode ?? $this->compareMode) {
            'last_year' => [$start->copy()->subYear(), $end->copy()->subYear()],
            'custom' => $this->validDate($this->compareFrom) && $this->validDate($this->compareTo) && $this->compareFrom <= $this->compareTo
                ? [Carbon::parse($this->compareFrom)->startOfDay(), Carbon::parse($this->compareTo)->endOfDay()]
                : $this->comparisonRange('previous'),
            // The same number of days, ending the day before this period starts.
            default => [$start->copy()->subDays((int) round($start->copy()->startOfDay()->diffInDays($end->copy()->startOfDay())) + 1)->startOfDay(), $start->copy()->subDay()->endOfDay()],
        };
    }

    /**
     * The selected range beside the comparison range, metric by metric.
     *
     * @return array{a_label: string, b_label: string, rows: list<array{label: string, a: ?float, b: ?float, money: bool, change: ?float, change_pct: ?float, better: ?bool}>}
     */
    public function getComparisonProperty(): array
    {
        [$bFrom, $bTo] = $this->comparisonRange();
        $key = $this->cacheKey('compare_'.$bFrom->toDateString().'_'.$bTo->toDateString());

        return Cache::remember($key, 300, function () use ($bFrom, $bTo) {
            $figures = app(\App\Services\PeriodFigures::class);
            $channel = $this->channelId();
            $a = $figures->totals($this->periodStart(), $this->periodEnd(), $channel);
            $b = $figures->totals($bFrom, $bTo, $channel);

            $rows = [];
            foreach ([
                ['shows', 'Shows', false, true], ['hours', 'Hours worked', false, true],
                ['gross', 'Gross sales', true, true], ['net', 'Net (after Whatnot fees)', true, true],
                ['gross_per_hour', 'Gross per hour', true, true], ['units', 'Units sold', false, true],
                ['tips', 'Tips', true, true], ['cogs', 'Cost of goods', true, false],
                ['pay', 'Streamer pay', true, false], ['profit', 'Profit', true, true],
                ['margin_pct', 'Profit margin %', false, true],
            ] as [$field, $label, $money, $higherIsBetter]) {
                $va = $a[$field] ?? null;
                $vb = $b[$field] ?? null;
                $change = $va !== null && $vb !== null ? round($va - $vb, 2) : null;
                $rows[] = [
                    'label' => $label, 'a' => $va, 'b' => $vb, 'money' => $money,
                    'change' => $change,
                    'change_pct' => $change !== null && $vb && $field !== 'margin_pct' ? round($change / abs($vb) * 100, 1) : null,
                    'better' => $change === null || $change == 0 ? null : ($change > 0) === $higherIsBetter,
                ];
            }

            $label = fn (Carbon $f, Carbon $t) => $f->format('M j, Y').' – '.$t->format('M j, Y');

            return ['a_label' => $label($this->periodStart(), $this->periodEnd()), 'b_label' => $label($bFrom, $bTo), 'rows' => $rows];
        });
    }

    // ── Channel profit ────────────────────────────────────────────────────────

    public function getChannelProfitProperty(): array
    {
        return Cache::remember($this->cacheKey('channel_profit'), 300, fn () => app(\App\Services\PeriodFigures::class)
            ->byChannel($this->periodStart(), $this->periodEnd(), $this->channelId()));
    }

    // ── Revenue by channel ────────────────────────────────────────────────────

    public function getRevenueByChannelProperty(): array
    {
        return Cache::remember($this->cacheKey('channel'), 300, function () {
            return DB::table('shows')
                ->leftJoin('whatnot_channels', 'shows.whatnot_channel_id', '=', 'whatnot_channels.id')
                ->whereBetween('shows.show_date', [$this->periodStart(), $this->periodEnd()])
                ->whereNotIn('shows.status', ['cancelled'])
                ->when($this->channelId() !== null, fn ($q) => $q->where('shows.whatnot_channel_id', $this->channelId()))
                ->selectRaw('
                    COALESCE(whatnot_channels.name, "Unknown") as channel,
                    COUNT(*) as shows,
                    COALESCE(SUM(shows.gross_revenue), 0) as gross,
                    COALESCE(SUM(shows.whatnot_net), 0) as net,
                    COALESCE(SUM(shows.units_sold), 0) as units
                ')
                ->groupBy('whatnot_channels.id', 'whatnot_channels.name')
                ->orderByDesc('gross')
                ->get()
                ->map(fn ($r) => (array) $r)
                ->all();
        });
    }

    // ── Revenue by week ───────────────────────────────────────────────────────

    public function getRevenueByWeekProperty(): array
    {
        return Cache::remember($this->cacheKey('week'), 300, function () {
            $isSqlite = DB::connection()->getDriverName() === 'sqlite';

            $weekExpr = $isSqlite
                ? "date(show_date, '-' || ((strftime('%w', show_date) + 6) % 7) || ' days')"
                : 'DATE_SUB(show_date, INTERVAL WEEKDAY(show_date) DAY)';

            return DB::table('shows')
                ->whereBetween('show_date', [$this->periodStart(), $this->periodEnd()])
                ->whereNotIn('status', ['cancelled'])
                ->when($this->channelId() !== null, fn ($q) => $q->where('whatnot_channel_id', $this->channelId()))
                ->selectRaw("
                    {$weekExpr} as week_start,
                    COUNT(*) as shows,
                    COALESCE(SUM(gross_revenue), 0) as gross,
                    COALESCE(SUM(whatnot_net), 0) as net,
                    COALESCE(SUM(units_sold), 0) as units
                ")
                ->groupByRaw($weekExpr)
                ->orderBy('week_start')
                ->get()
                ->map(fn ($r) => [
                    'week_start' => $r->week_start,
                    'week'       => Carbon::parse($r->week_start)->format('M j'),
                    'shows'      => (int) $r->shows,
                    'gross'      => (float) $r->gross,
                    'net'        => (float) $r->net,
                    'units'      => (int) $r->units,
                ])
                ->all();
        });
    }

    // ── Streamer performance ─────────────────────────────────────────────────
    public function getTopStreamersByPayoutProperty(): array
    {
        $sort = array_key_exists($this->streamerSort, self::STREAMER_SORTS) ? $this->streamerSort : 'gross';

        return Cache::remember($this->cacheKey('streamers_'.$sort), 300, function () use ($sort) {
            return DB::table('show_streamer')
                ->join('shows', 'show_streamer.show_id', '=', 'shows.id')
                ->join('streamers', 'show_streamer.streamer_id', '=', 'streamers.id')
                ->where('show_streamer.is_primary', true)
                ->whereBetween('shows.show_date', [$this->periodStart(), $this->periodEnd()])
                ->whereNotIn('shows.status', ['cancelled'])
                ->when($this->channelId() !== null, fn ($q) => $q->where('shows.whatnot_channel_id', $this->channelId()))
                ->selectRaw('streamers.name as streamer, COUNT(DISTINCT shows.id) as shows, COALESCE(SUM(shows.gross_revenue),0) as gross, COALESCE(SUM(shows.whatnot_net),0) as net')
                ->groupBy('show_streamer.streamer_id', 'streamers.name')->orderByDesc($sort)->orderByDesc('gross')->limit(10)->get()
                ->map(fn ($r) => ['streamer'=>$r->streamer,'shows'=>(int)$r->shows,'gross'=>(float)$r->gross,'net'=>(float)$r->net])->all();
        });
    }

    // ── Payout summary by status ──────────────────────────────────────────────

    public function getPayoutStatusSummaryProperty(): array
    {
        return Cache::remember($this->cacheKey('payout_status'), 300, function () {
            $rows = DB::table('payouts')
                ->join('shows', 'payouts.show_id', '=', 'shows.id')
                ->whereBetween('shows.show_date', [$this->periodStart(), $this->periodEnd()])
                ->when($this->channelId() !== null, fn ($q) => $q->where('shows.whatnot_channel_id', $this->channelId()))
                ->selectRaw('
                    payouts.status,
                    COUNT(*) as cnt,
                    COALESCE(SUM(payouts.calculated_payout), 0) as total
                ')
                ->groupBy('payouts.status')
                ->get()
                ->keyBy('status');

            $get = fn ($key) => [
                'count' => (int) ($rows[$key]->cnt ?? 0),
                'total' => (float) ($rows[$key]->total ?? 0),
            ];

            return [
                'draft'    => $get('draft'),
                'approved' => $get('approved'),
                'paid'     => $get('paid'),
            ];
        });
    }
}