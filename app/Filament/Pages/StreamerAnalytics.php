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
            && ! NavVisibility::isHiddenForUser(static::class, $user)
            && (bool) $user?->isAdmin();
    }

    public function getView(): string
    {
        return 'filament.pages.streamer-analytics';
    }

    public function getSubheading(): ?string
    {
        return 'Compare streamers side by side — revenue, margin, hours, and payout, over a date range you pick.';
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
        return Streamer::where('status', 'active')->inChannelContext()->orderBy('name')->get(['id', 'name']);
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
        return Cache::remember($this->cacheKey('rows'), 60, fn () => $this->buildAnalyticsRows());
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
                    $q->whereBetween('show_date', [$from, $toBound]);
                },
                'shows.orders:id,show_id,total_cost',
                'payouts' => function ($q) use ($from, $toBound) {
                    $q->whereHas('show', fn ($s) => $s->whereBetween('show_date', [$from, $toBound]));
                },
                'streamerLogEntries' => function ($q) use ($from, $toBound) {
                    $q->whereHas('show', fn ($s) => $s->whereBetween('show_date', [$from, $toBound]));
                },
            ]);

        if (! empty($this->selectedStreamers)) {
            $query->whereIn('id', $this->selectedStreamers);
        }

        $priorGrossByStreamer = $this->priorPeriodGrossByStreamer($from, $to);

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

            $rows[] = [
                'streamer_id'  => $streamer->id,
                'name'         => $streamer->name,
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

        return DB::table('show_streamer')
            ->join('shows', 'show_streamer.show_id', '=', 'shows.id')
            ->whereBetween('shows.show_date', [$priorFrom->toDateString(), $priorTo->copy()->endOfDay()->toDateTimeString()])
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

        $shows = Show::whereBetween('show_date', [$from, $toBound])
            ->inChannelContext()
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
