<?php

namespace App\Filament\Pages;

use App\Filament\Resources\ShowResource;
use App\Models\Show;
use Carbon\Carbon;
use Filament\Pages\Page;
use Livewire\Attributes\Url;
use Livewire\Attributes\Computed;
use Illuminate\Support\Facades\DB;

class ShowDataAudit extends Page
{
    protected static ?string $title = 'Show Data Audit';
    protected static ?string $navigationLabel = 'Show Data Audit';
    protected static ?string $slug = 'show-data-audit';
    protected string $view = 'filament.pages.show-data-audit';

    #[Url(as: 'range')]
    public string $datePreset = 'this_month';
    #[Url(as: 'from')]
    public string $dateFrom = '';
    #[Url(as: 'to')]
    public string $dateTo = '';
    #[Url(as: 'status')]
    public string $statusFilter = 'all';
    #[Url(as: 'page')]
    public int $followUpPage = 1;

    // Applied values are deliberately separate from the editable controls.
    // Audit queries only change after Apply Filters is clicked.
    public string $appliedDateFrom = '';
    public string $appliedDateTo = '';

    private const FOLLOW_UP_PER_PAGE = 50;

    public function mount(): void
    {
        abort_unless(auth()->user()?->isAdmin(), 403);
        if ($this->dateFrom === '' || $this->dateTo === '') $this->applyDatePreset($this->datePreset);
        $this->appliedDateFrom = $this->dateFrom;
        $this->appliedDateTo = $this->dateTo;
    }

    public function setStatusFilter(string $status): void
    {
        $this->statusFilter = $status;
        $this->followUpPage = 1;
    }

    public static function canAccess(): bool { return auth()->user()?->isAdmin() ?? false; }
    public static function shouldRegisterNavigation(): bool { return auth()->user()?->isAdmin() ?? false; }
    public static function getNavigationIcon(): string|\BackedEnum|null { return 'heroicon-o-chart-bar-square'; }
    public static function getNavigationGroup(): string|\UnitEnum|null { return 'Super Admin'; }
    public static function getNavigationSort(): ?int { return 21; }

    public function updatedDatePreset(string $value): void
    {
        if ($value !== 'custom') $this->applyDatePreset($value);
    }

    public function updatedDateFrom(): void
    {
        $this->datePreset = 'custom';
    }

    public function updatedDateTo(): void
    {
        $this->datePreset = 'custom';
    }

    public function applyFilters(): void
    {
        try {
            $from = Carbon::parse($this->dateFrom)->toDateString();
            $to = Carbon::parse($this->dateTo)->toDateString();
        } catch (\Throwable) {
            return;
        }

        if ($from > $to) {
            [$from, $to] = [$to, $from];
            $this->dateFrom = $from;
            $this->dateTo = $to;
        }

        $this->appliedDateFrom = $from;
        $this->appliedDateTo = $to;
        $this->followUpPage = 1;
    }

    public function applyDatePreset(string $preset): void
    {
        $today = today();
        [$from, $to] = match ($preset) {
            'last_month' => [$today->copy()->subMonthNoOverflow()->startOfMonth(), $today->copy()->subMonthNoOverflow()->endOfMonth()],
            'last_30' => [$today->copy()->subDays(29), $today],
            'last_90' => [$today->copy()->subDays(89), $today],
            'custom' => [
                Carbon::parse($this->dateFrom ?: $today->copy()->startOfMonth()),
                Carbon::parse($this->dateTo ?: $today),
            ],
            default => [$today->copy()->startOfMonth(), $today->copy()->endOfMonth()],
        };
        $this->datePreset = $preset;
        $this->dateFrom = $from->toDateString();
        $this->dateTo = $to->toDateString();

        if ($preset !== 'custom') {
            $this->appliedDateFrom = $this->dateFrom;
            $this->appliedDateTo = $this->dateTo;
        }
        $this->followUpPage = 1;
    }

    private function range(): array
    {
        try { $from = Carbon::parse($this->appliedDateFrom ?: $this->dateFrom)->startOfDay(); } catch (\Throwable) { $from = today()->startOfMonth(); }
        try { $to = Carbon::parse($this->appliedDateTo ?: $this->dateTo)->endOfDay(); } catch (\Throwable) { $to = today()->endOfMonth(); }
        if ($from->gt($to)) [$from, $to] = [$to, $from];
        return [$from, $to];
    }

    #[Computed]
    public function auditData(): array
    {
        [$from, $to] = $this->range();
        $fromDate = $from->toDateString();
        $toDate = $to->toDateString();
        $pastTo = min($toDate, today()->subDay()->toDateString());

        $base = Show::query()->inChannelContext()
            ->whereBetween('shows.show_date', [$fromDate, $toDate])
            ->whereNotIn('shows.status', ['cancelled']);

        // Keep the large-range audit cheap: totals and classifications are
        // calculated in SQL. Only the current 50-row follow-up page is hydrated.
        $summary = (clone $base)->selectRaw(
            'COUNT(*) as total, COALESCE(SUM(gross_revenue),0) as gross, '.
            'COALESCE(SUM(whatnot_net),0) as estimated_net, '.
            'COALESCE(SUM(completed_earnings),0) as completed, '.
            'COALESCE(SUM(show_duration),0) as duration_minutes'
        )->first();

        $verifiedSql = "(last_analytics_synced_at IS NOT NULL OR analytics_sync_status = 'complete' OR JSON_UNQUOTE(JSON_EXTRACT(raw_import_payload, '$.source')) = 'whatnot_analytics_csv')";
        $uuidSql = "CONCAT(COALESCE(whatnot_show_id,''),' ',COALESCE(detail_url,'')) REGEXP '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}'";

        $fieldCoverage = function (string $field) use ($base, $verifiedSql) {
            $total = (clone $base)->count();
            $present = $field === 'completed_earnings'
                ? (clone $base)->whereNotNull($field)->count()
                : (clone $base)->whereNotNull($field)->whereRaw($verifiedSql)->count();
            return ['present' => $present, 'missing' => $total - $present];
        };

        $coverage = [
            'gross_revenue' => $fieldCoverage('gross_revenue'),
            'whatnot_net' => $fieldCoverage('whatnot_net'),
            'show_duration' => $fieldCoverage('show_duration'),
        ];
        $settlementCoverage = $fieldCoverage('completed_earnings');

        $past = (clone $base)->whereDate('shows.show_date', '<=', $pastTo);
        $completeSql = "$verifiedSql AND gross_revenue IS NOT NULL AND show_duration IS NOT NULL AND (whatnot_net IS NOT NULL OR completed_earnings IS NOT NULL)";
        $hostedSql = "$completeSql AND show_duration > 0";
        $excludedSql = "show_duration = 0 AND $verifiedSql";

        $hostedTotal = (clone $past)->whereRaw($hostedSql)->count();
        $excludedTotal = (clone $past)->whereRaw($excludedSql)->count();
        $pastTotal = (clone $past)->count();
        $unresolvedPopulation = max(0, $pastTotal - $hostedTotal - $excludedTotal);

        $channelRows = (clone $past)
            ->leftJoin('whatnot_channels as wc', 'shows.whatnot_channel_id', '=', 'wc.id')
            ->selectRaw(
                "shows.whatnot_channel_id, COALESCE(wc.name, 'Unknown channel') as name, COUNT(*) as records, ".
                "SUM(CASE WHEN $hostedSql THEN 1 ELSE 0 END) as hosted, ".
                "SUM(CASE WHEN $excludedSql THEN 1 ELSE 0 END) as excluded, ".
                "SUM(CASE WHEN NOT ($hostedSql) AND NOT ($excludedSql) THEN 1 ELSE 0 END) as unresolved, ".
                "COALESCE(SUM(CASE WHEN $hostedSql THEN gross_revenue ELSE 0 END),0) as gross, ".
                "COALESCE(SUM(CASE WHEN $hostedSql THEN whatnot_net ELSE 0 END),0) as net, ".
                "COALESCE(SUM(CASE WHEN $hostedSql THEN show_duration ELSE 0 END),0) / 60 as hours"
            )
            ->groupBy('shows.whatnot_channel_id', 'wc.name')
            ->orderBy('wc.name')
            ->get()
            ->map(fn ($row) => [
                'name' => $row->name,
                'records' => (int) $row->records,
                'hosted' => (int) $row->hosted,
                'excluded' => (int) $row->excluded,
                'unresolved' => (int) $row->unresolved,
                'gross' => (float) $row->gross,
                'net' => (float) $row->net,
                'hours' => (float) $row->hours,
            ]);

        $complete = (clone $past)->whereRaw($completeSql)->count();
        $unavailable = (clone $past)->where('analytics_sync_status', 'unavailable')->whereRaw("NOT ($completeSql)")->count();
        $partial = (clone $past)->whereNotNull('last_analytics_synced_at')->whereRaw("NOT ($completeSql)")->where(function ($q) {
            $q->whereNull('analytics_sync_status')->orWhere('analytics_sync_status', '!=', 'unavailable');
        })->count();
        $unclassified = max(0, $pastTotal - $complete - $unavailable - $partial);
        $statusCounts = compact('complete', 'partial', 'unavailable', 'unclassified');

        $followBase = (clone $past)->whereRaw("NOT ($completeSql)");
        $needsMatch = (clone $followBase)->whereRaw("NOT ($uuidSql)")->count();
        $needsAnalytics = (clone $followBase)->whereRaw($uuidSql)->count();

        if ($this->statusFilter === 'complete') {
            $followBase->whereRaw($completeSql);
        } elseif ($this->statusFilter === 'unavailable') {
            $followBase->where('analytics_sync_status', 'unavailable');
        } elseif ($this->statusFilter === 'partial') {
            $followBase->whereNotNull('last_analytics_synced_at')->where(function ($q) {
                $q->whereNull('analytics_sync_status')->orWhere('analytics_sync_status', '!=', 'unavailable');
            });
        } elseif ($this->statusFilter === 'unclassified') {
            $followBase->whereNull('last_analytics_synced_at')->where(function ($q) {
                $q->whereNull('analytics_sync_status')->orWhereNotIn('analytics_sync_status', ['complete', 'unavailable']);
            });
        }

        $followUpTotal = (clone $followBase)->count();
        $followUpPages = (int) max(1, ceil($followUpTotal / self::FOLLOW_UP_PER_PAGE));
        $followUpPage = min(max(1, $this->followUpPage), $followUpPages);
        $missing = (clone $followBase)
            ->with('channel')
            ->orderByDesc('show_date')->orderByDesc('start_time')
            ->forPage($followUpPage, self::FOLLOW_UP_PER_PAGE)
            ->get();

        return [
            'from' => $from, 'to' => $to, 'total' => (int) ($summary->total ?? 0),
            'gross' => (float) ($summary->gross ?? 0),
            'estimatedNet' => (float) ($summary->estimated_net ?? 0),
            'completed' => (float) ($summary->completed ?? 0),
            'hours' => (float) ($summary->duration_minutes ?? 0) / 60,
            'coverage' => $coverage, 'settlementCoverage' => $settlementCoverage, 'missing' => $missing,
            'hostedTotal' => $hostedTotal, 'excludedTotal' => $excludedTotal,
            'unresolvedPopulation' => $unresolvedPopulation, 'channelPopulation' => $channelRows,
            'statusCounts' => $statusCounts, 'needsMatch' => $needsMatch, 'needsAnalytics' => $needsAnalytics,
            'complete' => $complete,
            'followUpTotal' => $followUpTotal,
            'followUpPages' => $followUpPages,
            'followUpPage' => $followUpPage,
        ];
    }

    public function showUrl(int $id): string { return ShowResource::getUrl('view', ['record' => $id]); }

    public function goToFollowUpPage(int $page): void
    {
        $this->followUpPage = max(1, $page);
    }
}
