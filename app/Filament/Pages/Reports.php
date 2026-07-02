<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HasModuleAccess;
use App\Models\Payout;
use App\Models\Show;
use App\Models\Streamer;
use App\Support\AdminModules;
use Filament\Pages\Page;

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
    }

    // ── Revenue summary ───────────────────────────────────────────────────────

    public function getRevenueSummaryProperty(): array
    {
        $since = now()->subDays((int) $this->period);

        $shows = Show::where('show_date', '>=', $since)
            ->whereNotIn('status', ['cancelled'])
            ->get();

        return [
            'gross'       => $shows->sum('gross_revenue'),
            'net'         => $shows->sum('whatnot_net'),
            'tips'        => $shows->sum('tips'),
            'paper'       => $shows->sum('paper_sales_gross'),
            'shows'       => $shows->count(),
            'units'       => $shows->sum('units_sold'),
        ];
    }

    // ── Revenue by channel ────────────────────────────────────────────────────

    public function getRevenueByChannelProperty(): \Illuminate\Support\Collection
    {
        $since = now()->subDays((int) $this->period);

        return Show::with('channel')
            ->where('show_date', '>=', $since)
            ->whereNotIn('status', ['cancelled'])
            ->get()
            ->groupBy(fn ($s) => $s->channel?->name ?? 'Unknown')
            ->map(fn ($group, $channel) => [
                'channel' => $channel,
                'shows'   => $group->count(),
                'gross'   => $group->sum('gross_revenue'),
                'net'     => $group->sum('whatnot_net'),
                'units'   => $group->sum('units_sold'),
            ])
            ->sortByDesc('gross')
            ->values();
    }

    // ── Revenue by week ───────────────────────────────────────────────────────

    public function getRevenueByWeekProperty(): \Illuminate\Support\Collection
    {
        $since = now()->subDays((int) $this->period);

        return Show::where('show_date', '>=', $since)
            ->whereNotIn('status', ['cancelled'])
            ->get()
            ->groupBy(fn ($s) => $s->show_date->startOfWeek()->format('Y-m-d'))
            ->map(fn ($group, $week) => [
                'week'  => \Illuminate\Support\Carbon::parse($week)->format('M j'),
                'shows' => $group->count(),
                'gross' => $group->sum('gross_revenue'),
                'net'   => $group->sum('whatnot_net'),
                'units' => $group->sum('units_sold'),
            ])
            ->sortBy('week')
            ->values();
    }

    // ── Top streamers by payout ───────────────────────────────────────────────

    public function getTopStreamersByPayoutProperty(): \Illuminate\Support\Collection
    {
        $since = now()->subDays((int) $this->period);

        return Payout::with('streamer')
            ->whereHas('show', fn ($q) => $q->where('show_date', '>=', $since))
            ->get()
            ->groupBy('streamer_id')
            ->map(fn ($group) => [
                'streamer' => $group->first()->streamer?->name ?? 'Unknown',
                'shows'    => $group->pluck('show_id')->unique()->count(),
                'total'    => $group->sum('calculated_payout'),
                'avg'      => $group->avg('calculated_payout'),
            ])
            ->sortByDesc('total')
            ->take(10)
            ->values();
    }

    // ── Payout summary by status ──────────────────────────────────────────────

    public function getPayoutStatusSummaryProperty(): array
    {
        $since = now()->subDays((int) $this->period);

        $payouts = Payout::whereHas('show', fn ($q) => $q->where('show_date', '>=', $since))->get();

        return [
            'draft'    => ['count' => $payouts->where('status', 'draft')->count(),    'total' => $payouts->where('status', 'draft')->sum('calculated_payout')],
            'approved' => ['count' => $payouts->where('status', 'approved')->count(), 'total' => $payouts->where('status', 'approved')->sum('calculated_payout')],
            'paid'     => ['count' => $payouts->where('status', 'paid')->count(),     'total' => $payouts->where('status', 'paid')->sum('calculated_payout')],
        ];
    }
}
