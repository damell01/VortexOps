<?php

namespace App\Filament\Pages;

use App\Models\Payout;
use App\Models\Streamer;
use App\Services\FeatureFlagService;
use Filament\Pages\Page;

class StreamerAnalytics extends Page
{
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
        return FeatureFlagService::enabled('analytics')
            && ($user?->isAdmin() || $user?->isOwner());
    }

    public function getView(): string
    {
        return 'filament.pages.streamer-analytics';
    }

    public string $dateFrom = '';
    public string $dateTo   = '';

    /** @var array<int> */
    public array $selectedStreamers = [];

    public function mount(): void
    {
        $this->dateFrom = now()->startOfMonth()->toDateString();
        $this->dateTo   = now()->toDateString();
    }

    public function refreshData(): void
    {
        // Computed properties re-evaluate on next render; no-op needed.
    }

    public function getStreamersListProperty(): \Illuminate\Support\Collection
    {
        return Streamer::where('status', 'active')->orderBy('name')->get(['id', 'name']);
    }

    /**
     * @return array<array<string,mixed>>
     */
    public function getAnalyticsRowsProperty(): array
    {
        $from = $this->dateFrom ?: now()->startOfMonth()->toDateString();
        $to   = $this->dateTo   ?: now()->toDateString();

        $query = Streamer::query()
            ->where('status', 'active')
            ->with(['shows' => function ($q) use ($from, $to) {
                $q->whereBetween('show_date', [$from, $to]);
            }, 'payouts' => function ($q) use ($from, $to) {
                $q->whereHas('show', fn ($s) => $s->whereBetween('show_date', [$from, $to]));
            }]);

        if (! empty($this->selectedStreamers)) {
            $query->whereIn('id', $this->selectedStreamers);
        }

        $rows = [];

        foreach ($query->get() as $streamer) {
            $shows     = $streamer->shows;
            $payouts   = $streamer->payouts;
            $showCount = $shows->count();

            // Duration: show_duration is stored in minutes
            $totalMinutes = $shows->sum('show_duration') ?? 0;
            $totalHours   = round($totalMinutes / 60, 2);

            $grossRevenue = (float) $shows->sum('gross_revenue');
            $unitsSold    = (int)   $shows->sum('units_sold');
            $totalPayout  = (float) $payouts->sum('calculated_payout');

            // Net revenue estimate: use deduction_request COGS if available, else 70% estimate
            $netRevenue  = $grossRevenue > 0 ? round($grossRevenue * 0.7, 2) : 0;
            $businessNet = max(0, $netRevenue - $totalPayout);

            $gmvPerHour = $totalHours > 0 ? round($grossRevenue / $totalHours, 2) : 0;
            $aov        = $unitsSold  > 0 ? round($grossRevenue / $unitsSold,  2) : 0;

            $rows[] = [
                'streamer_id'  => $streamer->id,
                'name'         => $streamer->name,
                'show_count'   => $showCount,
                'total_hours'  => $totalHours,
                'gross_revenue'=> $grossRevenue,
                'net_revenue'  => $netRevenue,
                'gmv_per_hour' => $gmvPerHour,
                'aov'          => $aov,
                'total_payout' => $totalPayout,
                'business_net' => $businessNet,
                'units_sold'   => $unitsSold,
            ];
        }

        // Sort by GMV/hr desc
        usort($rows, fn ($a, $b) => $b['gmv_per_hour'] <=> $a['gmv_per_hour']);

        return $rows;
    }

    public function getTopStreamerProperty(): ?array
    {
        $rows = $this->getAnalyticsRowsProperty();
        return ! empty($rows) ? $rows[0] : null;
    }
}
