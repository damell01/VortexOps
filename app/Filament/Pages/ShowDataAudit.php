<?php

namespace App\Filament\Pages;

use App\Filament\Resources\ShowResource;
use App\Models\Show;
use Carbon\Carbon;
use Filament\Pages\Page;
use Livewire\Attributes\Url;

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

    private const FOLLOW_UP_PER_PAGE = 50;

    public function mount(): void
    {
        abort_unless(auth()->user()?->isAdmin(), 403);
        if ($this->dateFrom === '' || $this->dateTo === '') $this->applyDatePreset($this->datePreset);
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
        $this->followUpPage = 1;
    }

    public function updatedDateTo(): void
    {
        $this->datePreset = 'custom';
        $this->followUpPage = 1;
    }

    public function applyDatePreset(string $preset): void
    {
        $today = today();
        [$from, $to] = match ($preset) {
            'last_month' => [$today->copy()->subMonthNoOverflow()->startOfMonth(), $today->copy()->subMonthNoOverflow()->endOfMonth()],
            'last_30' => [$today->copy()->subDays(29), $today],
            'last_90' => [$today->copy()->subDays(89), $today],
            default => [$today->copy()->startOfMonth(), $today->copy()->endOfMonth()],
        };
        $this->datePreset = $preset;
        $this->dateFrom = $from->toDateString();
        $this->dateTo = $to->toDateString();
        $this->followUpPage = 1;
    }

    private function range(): array
    {
        try { $from = Carbon::parse($this->dateFrom)->startOfDay(); } catch (\Throwable) { $from = today()->startOfMonth(); }
        try { $to = Carbon::parse($this->dateTo)->endOfDay(); } catch (\Throwable) { $to = today()->endOfMonth(); }
        if ($from->gt($to)) [$from, $to] = [$to, $from];
        return [$from, $to];
    }

    public function getAuditData(): array
    {
        [$from, $to] = $this->range();
        $shows = Show::query()->inChannelContext()
            ->whereBetween('show_date', [$from->toDateString(), $to->toDateString()])
            ->whereNotIn('status', ['cancelled'])
            ->with('channel')
            ->orderByDesc('show_date')->orderByDesc('start_time')->get();

        $total = $shows->count();
        $field = function (string $name) use ($shows) {
            $present = $shows->filter(fn (Show $show) => $show->hasVerifiedAnalyticsField($name))->count();
            return ['present' => $present, 'missing' => $shows->count() - $present];
        };

        $coverage = [
            'gross_revenue' => $field('gross_revenue'),
            'whatnot_net' => $field('whatnot_net'),
            'show_duration' => $field('show_duration'),
        ];
        $settlementCoverage = $field('completed_earnings');

        $pastShows = $shows->filter(fn (Show $s) => $s->show_date?->lt(today()));

        // A Whatnot Shows CSV row is authoritative evidence that the show was
        // actually hosted. Keep raw database rows visible, but do not confuse
        // scheduled/imported shells with Whatnot's hosted-show population.
        $hostedShows = $pastShows->filter(fn (Show $s) =>
            $s->hasVerifiedAnalyticsField('show_duration')
            && (int) $s->show_duration > 0
            && $s->analyticsCoverageStatus() === 'complete'
        );
        $excludedShows = $pastShows->filter(fn (Show $s) =>
            $s->status === 'cancelled' || (
                $s->hasVerifiedAnalyticsField('show_duration') && (int) $s->show_duration === 0
            )
        );
        $unresolvedPopulation = $pastShows->reject(fn (Show $s) =>
            $hostedShows->contains('id', $s->id) || $excludedShows->contains('id', $s->id)
        );

        $channelPopulation = $pastShows->groupBy('whatnot_channel_id')->map(function ($channelShows) use ($hostedShows, $excludedShows, $unresolvedPopulation) {
            $channel = $channelShows->first()?->channel;
            $ids = $channelShows->pluck('id');
            $hosted = $hostedShows->whereIn('id', $ids);
            $excluded = $excludedShows->whereIn('id', $ids);
            $unresolved = $unresolvedPopulation->whereIn('id', $ids);

            return [
                'name' => $channel?->name ?: 'Unknown channel',
                'records' => $channelShows->count(),
                'hosted' => $hosted->count(),
                'excluded' => $excluded->count(),
                'unresolved' => $unresolved->count(),
                'gross' => (float) $hosted->sum('gross_revenue'),
                'net' => (float) $hosted->sum('whatnot_net'),
                'hours' => (float) $hosted->sum('show_duration') / 60,
            ];
        })->sortBy('name')->values();

        $statusCounts = [
            'complete' => $pastShows->filter(fn (Show $s) => $s->analyticsCoverageStatus() === 'complete')->count(),
            'partial' => $pastShows->filter(fn (Show $s) => $s->analyticsCoverageStatus() === 'partial')->count(),
            'unavailable' => $pastShows->filter(fn (Show $s) => $s->analyticsCoverageStatus() === 'unavailable')->count(),
            'unclassified' => $pastShows->filter(fn (Show $s) => $s->analyticsCoverageStatus() === 'unclassified')->count(),
        ];

        $needsFollowUp = $pastShows->filter(fn (Show $s) => $s->analyticsCoverageStatus() !== 'complete');
        $hasUuid = fn (Show $s) => (bool) preg_match(
            '/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/i',
            (string) ($s->whatnot_show_id ?: $s->detail_url)
        );
        $needsMatch = $needsFollowUp->reject($hasUuid)->count();
        $needsAnalytics = $needsFollowUp->filter($hasUuid)->count();

        if ($this->statusFilter !== 'all') {
            $needsFollowUp = $needsFollowUp->filter(fn (Show $s) => $s->analyticsCoverageStatus() === $this->statusFilter);
        }

        $needsFollowUp = $needsFollowUp->values();
        $followUpTotal = $needsFollowUp->count();
        $followUpPages = (int) max(1, ceil($followUpTotal / self::FOLLOW_UP_PER_PAGE));
        $followUpPage = min(max(1, $this->followUpPage), $followUpPages);
        $missing = $needsFollowUp->forPage($followUpPage, self::FOLLOW_UP_PER_PAGE)->values();

        return [
            'from' => $from, 'to' => $to, 'total' => $total,
            'gross' => (float) $shows->sum('gross_revenue'),
            'estimatedNet' => (float) $shows->sum('whatnot_net'),
            'completed' => (float) $shows->sum('completed_earnings'),
            'hours' => (float) $shows->sum('show_duration') / 60,
            'coverage' => $coverage, 'settlementCoverage' => $settlementCoverage, 'missing' => $missing,
            'hostedTotal' => $hostedShows->count(), 'excludedTotal' => $excludedShows->count(),
            'unresolvedPopulation' => $unresolvedPopulation->count(), 'channelPopulation' => $channelPopulation,
            'statusCounts' => $statusCounts, 'needsMatch' => $needsMatch, 'needsAnalytics' => $needsAnalytics,
            'complete' => $statusCounts['complete'],
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
