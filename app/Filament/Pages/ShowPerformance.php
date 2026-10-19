<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HasModuleAccess;
use App\Models\Business;
use App\Models\Show;
use App\Models\Streamer;
use App\Models\WhatnotChannel;
use App\Services\ProfitLossReport;
use App\Services\ShowPerformanceReport;
use App\Support\AdminModules;
use App\Support\BusinessTime;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;

/**
 * Show Performance — which days and times earn the most, and the shows that
 * did best. Net is Whatnot net by default; switch to business net (after
 * product cost and pay) for finalized shows. Rows come from Business
 * Profitability, so business net here is the same figure there.
 * Admin only: streamers never see cost or profit.
 */
class ShowPerformance extends Page
{
    use HasModuleAccess;

    protected static string $moduleSlug = 'reporting';
    protected static ?string $title = 'Show Performance';
    protected static ?string $slug = 'show-performance';
    protected static bool $shouldRegisterNavigation = false;

    public const RANGES = ['30' => 'Last 30 days', '90' => 'Last 90 days', '365' => 'Last 12 months', 'all' => 'All time'];

    #[Url]
    public string $range = '90';

    #[Url]
    public string $channel = '';

    #[Url]
    public string $business = '';

    #[Url]
    public string $streamer = '';

    /** whatnot | business — which net the page ranks on. */
    #[Url]
    public string $basis = 'whatnot';

    #[Url]
    public string $metric = 'net_hour';

    #[Url]
    public string $board = 'net';

    /** The heatmap cell open below the grid, as "day-block". */
    public ?string $cell = null;

    private ?Collection $rowsMemo = null;

    protected static function passesModuleAccessCheck(): bool
    {
        return (bool) (auth()->user()?->isAdmin() || auth()->user()?->isOwner());
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return AdminModules::navigationGroupFor('reporting');
    }

    public function getView(): string { return 'filament.pages.show-performance'; }

    public function getHeading(): string { return ''; }

    public function mount(): void
    {
        $this->range = array_key_exists($this->range, self::RANGES) ? $this->range : '90';
        $this->basis = array_key_exists($this->basis, ShowPerformanceReport::BASES) ? $this->basis : 'whatnot';
        $this->metric = array_key_exists($this->metric, ShowPerformanceReport::METRICS) ? $this->metric : 'net_hour';
        $this->board = array_key_exists($this->board, ShowPerformanceReport::BOARD) ? $this->board : 'net';
    }

    public function updated(string $name): void
    {
        if (in_array($name, ['range', 'channel', 'business', 'streamer', 'basis'], true)) {
            $this->rowsMemo = null;
            $this->cell = null;
        }
    }

    public function setBasis(string $basis): void
    {
        $this->basis = array_key_exists($basis, ShowPerformanceReport::BASES) ? $basis : 'whatnot';
        $this->rowsMemo = null;
    }

    /** "Whatnot net" or "Business net", for labels. */
    public function netLabel(): string
    {
        return ShowPerformanceReport::BASES[$this->basis];
    }

    public function setMetric(string $metric): void
    {
        $this->metric = array_key_exists($metric, ShowPerformanceReport::METRICS) ? $metric : 'net_hour';
    }

    public function setBoard(string $board): void
    {
        $this->board = array_key_exists($board, ShowPerformanceReport::BOARD) ? $board : 'net';
    }

    public function openCell(int $day, int $block): void
    {
        $key = "{$day}-{$block}";
        $this->cell = $this->cell === $key ? null : $key;
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private function period(): array
    {
        $to = BusinessTime::local()->startOfDay();
        if ($this->range === 'all') {
            $first = Show::query()->min('show_date');

            return [$first ? Carbon::parse($first)->startOfDay() : $to->copy()->subYear(), $to];
        }

        return [$to->copy()->subDays((int) $this->range - 1), $to];
    }

    public function periodLabel(): string
    {
        [$from, $to] = $this->period();

        return $from->format('M j, Y').' – '.$to->format('M j, Y');
    }

    public function rows(): Collection
    {
        if ($this->rowsMemo !== null) {
            return $this->rowsMemo;
        }
        [$from, $to] = $this->period();
        $rows = app(ProfitLossReport::class)->rows($from, $to, [
            'channel' => $this->channel !== '' ? (int) $this->channel : null,
            'business' => $this->business !== '' ? (int) $this->business : null,
            'streamer' => $this->streamer !== '' ? (int) $this->streamer : null,
        ]);

        // A show without logged hours still has a length: its recorded
        // duration, or its start and end times.
        $durations = Show::query()->whereKey($rows->pluck('id'))
            ->get(['id', 'show_duration', 'start_time', 'end_time'])
            ->mapWithKeys(function (Show $s) {
                $minutes = (float) $s->show_duration;
                if ($minutes <= 0 && $s->start_time && $s->end_time) {
                    $minutes = $s->start_time->diffInMinutes($s->end_time->lt($s->start_time) ? $s->end_time->copy()->addDay() : $s->end_time);
                }

                return [$s->id => $minutes];
            })->all();

        return $this->rowsMemo = app(ShowPerformanceReport::class)->enrich($rows, $durations, $this->basis);
    }

    public function totals(): array
    {
        return app(ShowPerformanceReport::class)->totals($this->rows());
    }

    public function heatmap(): array
    {
        return app(ShowPerformanceReport::class)->heatmap($this->rows());
    }

    public function bestTimes(array $heatmap): array
    {
        return app(ShowPerformanceReport::class)->bestTimes($heatmap, $this->metric);
    }

    public function leaderboard(): Collection
    {
        return app(ShowPerformanceReport::class)->leaderboard($this->rows(), $this->board);
    }

    /** The shows behind the open heatmap cell, best first. */
    public function cellShows(): Collection
    {
        if ($this->cell === null || ! preg_match('/^(\d)-(\d{1,2})$/', $this->cell, $m)) {
            return collect();
        }

        return $this->rows()->filter(fn ($r) => $r['day'] === (int) $m[1] && $r['block'] === (int) $m[2])
            ->sortByDesc(fn ($r) => $r['net'] ?? -INF)->values();
    }

    public function options(): array
    {
        return [
            'channels' => WhatnotChannel::orderBy('name')->pluck('name', 'id')->all(),
            'businesses' => Business::ordered()->pluck('name', 'id')->all(),
            'streamers' => Streamer::where('status', 'active')->orderBy('name')->pluck('name', 'id')->all(),
        ];
    }

    public function showUrl(int $id): string
    {
        return \App\Filament\Resources\ShowResource::getUrl('view', ['record' => $id]);
    }
}
