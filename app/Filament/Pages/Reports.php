<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HasModuleAccess;
use App\Support\AdminModules;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class Reports extends Page
{
    use HasModuleAccess;

    protected static string $moduleSlug = 'reporting';
    protected static ?string $title = 'Reports & Analytics';

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

    // ── Period ────────────────────────────────────────────────────────────────

    public string $period = '30';
    public bool $showAllWeeks = false;

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
        $this->period = $days;
        $this->showAllWeeks = false;
    }

    public function toggleAllWeeks(): void
    {
        $this->showAllWeeks = ! $this->showAllWeeks;
    }

    // ── Cache key ────────────────────────────────────────────────────────────

    private function cacheKey(string $section): string
    {
        return "reports_{$section}_{$this->period}_" . auth()->id();
    }

    // ── Revenue summary ───────────────────────────────────────────────────────

    public function getRevenueSummaryProperty(): array
    {
        return Cache::remember($this->cacheKey('summary'), 60, function () {
            $since = now()->subDays((int) $this->period);

            $cur = DB::table('shows')
                ->where('show_date', '>=', $since)
                ->whereNotIn('status', ['cancelled'])
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
                    now()->subDays((int) $this->period * 2),
                    now()->subDays((int) $this->period),
                ])
                ->whereNotIn('status', ['cancelled'])
                ->selectRaw('
                    COUNT(*) as shows,
                    COALESCE(SUM(gross_revenue), 0) as gross,
                    COALESCE(SUM(whatnot_net), 0) as net
                ')
                ->first();

            $trend = fn ($cur, $prev) => $prev > 0 ? round((($cur - $prev) / $prev) * 100, 1) : null;

            return [
                'gross'       => (float) ($cur->gross ?? 0),
                'net'         => (float) ($cur->net ?? 0),
                'tips'        => (float) ($cur->tips ?? 0),
                'paper'       => (float) ($cur->paper ?? 0),
                'shows'       => (int) ($cur->shows ?? 0),
                'units'       => (int) ($cur->units ?? 0),
                'trend_gross' => $trend($cur->gross ?? 0, $prev->gross ?? 0),
                'trend_net'   => $trend($cur->net ?? 0, $prev->net ?? 0),
                'trend_shows' => $trend($cur->shows ?? 0, $prev->shows ?? 0),
            ];
        });
    }

    // ── Revenue by channel ────────────────────────────────────────────────────

    public function getRevenueByChannelProperty(): array
    {
        return Cache::remember($this->cacheKey('channel'), 60, function () {
            $since = now()->subDays((int) $this->period);

            return DB::table('shows')
                ->leftJoin('whatnot_channels', 'shows.whatnot_channel_id', '=', 'whatnot_channels.id')
                ->where('shows.show_date', '>=', $since)
                ->whereNotIn('shows.status', ['cancelled'])
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
        return Cache::remember($this->cacheKey('week'), 60, function () {
            $since = now()->subDays((int) $this->period);

            return DB::table('shows')
                ->where('show_date', '>=', $since)
                ->whereNotIn('status', ['cancelled'])
                ->selectRaw("
                    DATE_SUB(show_date, INTERVAL WEEKDAY(show_date) DAY) as week_start,
                    COUNT(*) as shows,
                    COALESCE(SUM(gross_revenue), 0) as gross,
                    COALESCE(SUM(whatnot_net), 0) as net,
                    COALESCE(SUM(units_sold), 0) as units
                ")
                ->groupByRaw('week_start')
                ->orderBy('week_start')
                ->get()
                ->map(fn ($r) => [
                    'week_start' => $r->week_start,
                    'week'       => \Illuminate\Support\Carbon::parse($r->week_start)->format('M j'),
                    'shows'      => (int) $r->shows,
                    'gross'      => (float) $r->gross,
                    'net'        => (float) $r->net,
                    'units'      => (int) $r->units,
                ])
                ->all();
        });
    }

    // ── Top streamers by payout ───────────────────────────────────────────────

    public function getTopStreamersByPayoutProperty(): array
    {
        return Cache::remember($this->cacheKey('streamers'), 60, function () {
            $since = now()->subDays((int) $this->period);

            return DB::table('payouts')
                ->join('shows', 'payouts.show_id', '=', 'shows.id')
                ->join('streamers', 'payouts.streamer_id', '=', 'streamers.id')
                ->where('shows.show_date', '>=', $since)
                ->selectRaw('
                    streamers.name as streamer,
                    COUNT(DISTINCT payouts.show_id) as shows,
                    COALESCE(SUM(payouts.calculated_payout), 0) as total,
                    COALESCE(AVG(payouts.calculated_payout), 0) as avg
                ')
                ->groupBy('payouts.streamer_id', 'streamers.name')
                ->orderByDesc('total')
                ->limit(10)
                ->get()
                ->map(fn ($r) => (array) $r)
                ->all();
        });
    }

    // ── Payout summary by status ──────────────────────────────────────────────

    public function getPayoutStatusSummaryProperty(): array
    {
        return Cache::remember($this->cacheKey('payout_status'), 60, function () {
            $since = now()->subDays((int) $this->period);

            $rows = DB::table('payouts')
                ->join('shows', 'payouts.show_id', '=', 'shows.id')
                ->where('shows.show_date', '>=', $since)
                ->selectRaw('
                    payouts.status,
                    COUNT(*) as cnt,
                    COALESCE(SUM(payouts.calculated_payout), 0) as total
                ')
                ->groupBy('payouts.status')
                ->pluck(null, 'status');

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
