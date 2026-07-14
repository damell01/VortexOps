<?php

namespace App\Filament\Pages;

use App\Models\Payout;
use App\Models\Show;
use App\Models\Streamer;
use App\Models\StreamerLogEntry;
use App\Support\AdminModules;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
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
        $user = auth()->user();

        return AdminModules::isEnabled('reporting')
            && AdminModules::isFeatureEnabled('streamer_analytics')
            && (bool) $user?->isAdmin();
    }

    public function getView(): string
    {
        return 'filament.pages.streamer-analytics';
    }

    public string $dateFrom = '';
    public string $dateTo   = '';
    public string $activeTab = 'overview';

    /** @var array<int> */
    public array $selectedStreamers = [];

    public function mount(): void
    {
        $this->dateFrom = now()->startOfYear()->toDateString();
        $this->dateTo   = now()->toDateString();
    }

    public function refreshData(): void {}

    public function getStreamersListProperty(): Collection
    {
        return Streamer::where('status', 'active')->orderBy('name')->get(['id', 'name']);
    }

    /**
     * @return array<array<string,mixed>>
     */
    public function getAnalyticsRowsProperty(): array
    {
        $from = $this->dateFrom ?: now()->startOfYear()->toDateString();
        $to   = $this->dateTo   ?: now()->toDateString();

        $query = Streamer::query()
            ->where('status', 'active')
            ->with([
                'shows' => function ($q) use ($from, $to) {
                    $q->whereBetween('show_date', [$from, $to]);
                },
                'payouts' => function ($q) use ($from, $to) {
                    $q->whereHas('show', fn ($s) => $s->whereBetween('show_date', [$from, $to]));
                },
                'streamerLogEntries' => function ($q) use ($from, $to) {
                    $q->whereHas('show', fn ($s) => $s->whereBetween('show_date', [$from, $to]));
                },
            ]);

        if (! empty($this->selectedStreamers)) {
            $query->whereIn('id', $this->selectedStreamers);
        }

        $rows = [];

        foreach ($query->get() as $streamer) {
            $shows   = $streamer->shows;
            $payouts = $streamer->payouts;
            $logs    = $streamer->streamerLogEntries;

            $showCount    = $shows->count();
            $grossRevenue = (float) $shows->sum('gross_revenue');
            $netRevenue   = (float) $shows->sum('whatnot_net');
            $tips         = (float) $payouts->sum('tips');
            $unitsSold    = (int) $shows->sum('units_sold');
            $totalPayout  = (float) $payouts->sum('calculated_payout');
            $totalPaid    = (float) $payouts->where('status', 'paid')->sum('calculated_payout');

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

            // Outstanding balance from streamer totals
            $balance = (float) ($streamer->total_earnings_due ?? 0) - (float) ($streamer->total_earnings_paid ?? 0);

            $rows[] = [
                'streamer_id'  => $streamer->id,
                'name'         => $streamer->name,
                'payout_type'  => $streamer->payout_type,
                'show_count'   => $showCount,
                'total_hours'  => $totalHours,
                'gross_revenue'=> $grossRevenue,
                'net_revenue'  => $netRevenue,
                'tips'         => $tips,
                'gmv_per_hour' => $gmvPerHour,
                'aov'          => $aov,
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
     * Weekly breakdown: aggregates gross, net, hours, and SPH per week.
     * @return array<array<string,mixed>>
     */
    public function getWeeklyRowsProperty(): array
    {
        $from = $this->dateFrom ?: now()->startOfYear()->toDateString();
        $to   = $this->dateTo   ?: now()->toDateString();

        $shows = Show::whereBetween('show_date', [$from, $to])
            ->with('streamerLogEntry')
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
}
