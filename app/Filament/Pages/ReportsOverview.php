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

    /** Links that carry this period to the report they open. */
    public function links(): array
    {
        [$from, $to] = $this->period();
        $business = $this->business !== '' ? ['business' => $this->business] : [];
        $pl = fn (string $tab) => ProfitLoss::getUrl(['period' => $this->range === 'this_year' ? 'year' : ($from->diffInDays($to) > 7 ? 'month' : 'week'), 'anchor' => $from->toDateString(), 'tab' => $tab] + $business);

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
