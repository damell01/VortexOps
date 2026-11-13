<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HasModuleAccess;
use App\Models\Business;
use App\Services\ProfitLossReport;
use App\Support\AdminModules;
use App\Support\BusinessTime;
use App\Support\NotificationSchedule;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;

/**
 * Reports Overview — the front door to reporting: the period's headline
 * numbers, what needs attention (negative Whatnot earnings, finalized
 * losses, shows still waiting on costs or pay) and every report.
 * Figures come from ProfitLossReport, the same rows as Profit & Loss.
 */
class ReportsOverview extends Page
{
    use HasModuleAccess;

    protected static string $moduleSlug = 'reporting';
    protected static ?string $title = 'Reports Overview';
    protected static ?string $slug = 'reports-overview';
    protected static bool $shouldRegisterNavigation = false;

    public const RANGES = ['this_week' => 'This Week', 'last_week' => 'Last Week', 'this_month' => 'This Month', 'last_month' => 'Last Month', 'last_30' => 'Last 30 Days', 'this_year' => 'This Year'];

    #[Url]
    public string $range = 'this_month';

    #[Url]
    public string $business = '';

    private ?Collection $rowsMemo = null;
    private ?Collection $prevMemo = null;
    private ?array $flowMemo = null;

    protected static function passesModuleAccessCheck(): bool
    {
        return (bool) (auth()->user()?->isAdmin() || auth()->user()?->isOwner());
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return AdminModules::navigationGroupFor('reporting');
    }

    public function getView(): string { return 'filament.pages.reports-overview'; }

    public function getHeading(): string { return ''; }

    public function mount(): void
    {
        if (! array_key_exists($this->range, self::RANGES)) {
            $this->range = 'this_month';
        }
    }

    /** @return array{0:Carbon,1:Carbon} */
    public function period(): array
    {
        $today = Carbon::parse(BusinessTime::local()->toDateString());

        return match ($this->range) {
            'this_week' => [$today->copy()->startOfWeek(), $today],
            'last_week' => [$today->copy()->startOfWeek()->subWeek(), $today->copy()->startOfWeek()->subDay()],
            'last_month' => [$today->copy()->subMonthNoOverflow()->startOfMonth(), $today->copy()->subMonthNoOverflow()->endOfMonth()->startOfDay()],
            'last_30' => [$today->copy()->subDays(29), $today],
            'this_year' => [$today->copy()->startOfYear(), $today],
            default => [$today->copy()->startOfMonth(), $today],
        };
    }

    public function periodLabel(): string
    {
        [$from, $to] = $this->period();

        return NotificationSchedule::rangeLabel($from, $to);
    }

    public function updated(): void
    {
        $this->rowsMemo = null;
        $this->prevMemo = null;
        $this->flowMemo = null;
    }

    public function rows(): Collection
    {
        [$from, $to] = $this->period();

        return $this->rowsMemo ??= app(ProfitLossReport::class)->rows($from, $to, ['business' => $this->business !== '' ? (int) $this->business : null]);
    }

    public function totals(): array
    {
        $rows = $this->rows();

        return app(ProfitLossReport::class)->totals($rows) + [
            'whatnot_net' => round((float) $rows->sum('whatnot_net'), 2),
        ];
    }

    /** Whatnot's estimated earnings per day (per month for a year). */
    public function trend(): array
    {
        [$from, $to] = $this->period();
        $monthly = $from->diffInDays($to) > 62;
        $buckets = [];
        for ($d = $from->copy(); $d->lte($to); $monthly ? $d->addMonthNoOverflow()->startOfMonth() : $d->addDay()) {
            $key = $monthly ? $d->format('Y-m') : $d->toDateString();
            $buckets[$key] ??= ['label' => $monthly ? $d->format('M') : $d->format('M j'), 'value' => 0.0, 'shows' => 0];
        }
        foreach ($this->rows() as $r) {
            $key = $monthly ? $r['date']?->format('Y-m') : $r['date']?->toDateString();
            if ($key !== null && isset($buckets[$key])) {
                $buckets[$key]['value'] += $r['whatnot_net'];
                $buckets[$key]['shows']++;
            }
        }

        return array_values(array_map(fn ($b) => $b + ['title' => $b['label'].': $'.number_format($b['value'], 2).' from '.$b['shows'].' '.str('show')->plural($b['shows'])], $buckets));
    }

    /** The same number of days just before this period. */
    public function previousRows(): Collection
    {
        [$from, $to] = $this->period();
        $days = (int) $from->diffInDays($to) + 1;

        return $this->prevMemo ??= app(ProfitLossReport::class)->rows($from->copy()->subDays($days), $from->copy()->subDay(), ['business' => $this->business !== '' ? (int) $this->business : null]);
    }

    /** Headline figures with change against the period before and a daily trend line. */
    public function kpis(): array
    {
        $cur = $this->rows(); $prev = $this->previousRows();
        $flow = $this->flow();
        $chg = fn ($a, $b) => $b != 0 ? round(($a - $b) / abs($b) * 100, 1) : null;
        $sum = fn ($rows, $k) => (float) $rows->sum($k);
        $hours = fn ($rows) => (float) $rows->sum(fn ($r) => $r['hours'] ?? $r['duration_hours'] ?? 0);
        $nph = fn ($rows) => $hours($rows) > 0 ? $sum($rows, 'whatnot_net') / $hours($rows) : 0;

        return [
            'sales' => ['Gross sales', $sum($cur, 'sales'), $chg($sum($cur, 'sales'), $sum($prev, 'sales')), $flow['sales'], 'heroicon-o-shopping-bag', true],
            'net' => ['Whatnot net', $sum($cur, 'whatnot_net'), $chg($sum($cur, 'whatnot_net'), $sum($prev, 'whatnot_net')), $flow['net'], 'heroicon-o-banknotes', true],
            'profit' => ['Profit (est.)', $sum($cur, 'profit'), $chg($sum($cur, 'profit'), $sum($prev, 'profit')), $flow['profit'], 'heroicon-o-arrow-trending-up', true],
            'nph' => ['Net per hour', $nph($cur), $chg($nph($cur), $nph($prev)), null, 'heroicon-o-clock', true],
            'shows' => ['Shows', $cur->count(), $chg($cur->count(), $prev->count()), $flow['shows'], 'heroicon-o-video-camera', false],
        ];
    }

    /** Gross, net, profit and show count per day (per week past 45 days, per month for a year). */
    public function flow(): array
    {
        if ($this->flowMemo) return $this->flowMemo;
        [$from, $to] = $this->period();
        $days = (int) $from->diffInDays($to) + 1;
        $unit = $days > 120 ? 'month' : ($days > 45 ? 'week' : 'day');
        $key = fn (Carbon $d) => match ($unit) { 'month' => $d->format('Y-m'), 'week' => $d->copy()->startOfWeek()->toDateString(), default => $d->toDateString() };
        $buckets = [];
        for ($d = $from->copy(); $d->lte($to); $d->addDay()) {
            $k = $key($d);
            $buckets[$k] ??= ['label' => match ($unit) { 'month' => $d->format('M'), 'week' => $d->copy()->startOfWeek()->format('M j'), default => $d->format('M j') }, 'sales' => 0.0, 'net' => 0.0, 'profit' => 0.0, 'shows' => 0];
        }
        foreach ($this->rows() as $r) {
            $k = $r['date'] ? $key($r['date']) : null;
            if ($k === null || ! isset($buckets[$k])) continue;
            $buckets[$k]['sales'] += $r['sales']; $buckets[$k]['net'] += $r['whatnot_net']; $buckets[$k]['profit'] += $r['profit']; $buckets[$k]['shows']++;
        }
        $b = array_values($buckets);

        return $this->flowMemo = ['unit' => $unit, 'labels' => array_column($b, 'label'), 'sales' => array_column($b, 'sales'), 'net' => array_column($b, 'net'), 'profit' => array_column($b, 'profit'), 'shows' => array_column($b, 'shows')];
    }

    /** Whatnot net by channel, biggest first. */
    public function channels(): array
    {
        return $this->rows()->groupBy('channel')->map(fn ($g, $name) => ['label' => $name, 'value' => round((float) $g->sum('whatnot_net'), 2), 'id' => $g->first()['channel_id']])
            ->sortByDesc('value')->values()->all();
    }

    /** Top streamers by Whatnot net, with show count and net per hour. */
    public function streamers(): array
    {
        return $this->rows()->filter(fn ($r) => $r['streamer_id'])->groupBy('streamer_id')->map(function ($g) {
            $h = (float) $g->sum(fn ($r) => $r['hours'] ?? $r['duration_hours'] ?? 0);
            return ['id' => $g->first()['streamer_id'], 'name' => $g->first()['streamer'], 'net' => round((float) $g->sum('whatnot_net'), 2), 'shows' => $g->count(), 'nph' => $h > 0 ? round($g->sum('whatnot_net') / $h, 0) : null];
        })->sortByDesc('net')->values()->take(6)->all();
    }

    /** The latest shows in the period. */
    public function recent(): Collection
    {
        return $this->rows()->sortByDesc(fn ($r) => ($r['date']?->timestamp ?? 0) * 100000 + $r['id'])->take(8)->values();
    }

    /** This month's goals with progress. */
    public function goals(): Collection
    {
        return app(\App\Services\GoalProgress::class)->forMonth(now()->startOfMonth());
    }

    /** Links that carry this period to the report they open. */
    public function links(): array
    {
        [$from, $to] = $this->period();
        $business = $this->business !== '' ? ['business' => $this->business] : [];
        $pl = fn (string $tab) => ProfitLoss::getUrl(['period' => 'custom', 'from' => $from->toDateString(), 'to' => $to->toDateString(), 'tab' => $tab] + $business);

        return [
            'losses' => WhatnotLosses::getUrl(['range' => 'custom', 'from' => $from->toDateString(), 'to' => $to->toDateString()] + $business),
            'finalized_losses' => $pl('losses'),
            'pending' => $pl('incomplete'),
            'pl' => $pl('all'),
        ];
    }

    /** Every report this person can open, for the library. */
    public function library(): array
    {
        $tiles = [
            [Reports::class, 'Whatnot Performance', 'Sales, fees and estimated earnings', 'heroicon-o-chart-bar', 'accent'],
            [ProfitLoss::class, 'Business Profitability', 'Profit after costs and completed pay runs', 'heroicon-o-currency-dollar', 'ok'],
            [ShowPerformance::class, 'Show Performance', 'Best times to stream and top shows', 'heroicon-o-fire', 'warn'],
            [StreamerAnalytics::class, 'Streamer Analytics', 'Performance by streamer', 'heroicon-o-user-group', 'info'],
            [InventoryReport::class, 'Inventory Reports', 'Stock value, movement and aging', 'heroicon-o-cube', 'warn'],
            [\App\Filament\Resources\WhatnotLedgerResource::class, 'Ledger', 'Detailed financial entries', 'heroicon-o-document-text', 'accent'],
            [WhatnotLosses::class, 'Whatnot Losses', 'Shows with negative estimated earnings', 'heroicon-o-arrow-trending-down', 'bad'],
        ];
        $out = [];
        foreach ($tiles as [$class, $title, $text, $icon, $tone]) {
            try {
                if (! $class::canAccess()) {
                    continue;
                }
                $url = is_subclass_of($class, \Filament\Resources\Resource::class) ? $class::getUrl('index') : $class::getUrl();
            } catch (\Throwable) {
                continue;
            }
            $out[] = compact('title', 'text', 'icon', 'tone', 'url');
        }

        return $out;
    }

    public function businesses(): array
    {
        return Business::ordered()->pluck('name', 'id')->all();
    }
}
