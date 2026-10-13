<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HasModuleAccess;
use App\Models\Business;
use App\Models\Streamer;
use App\Models\WhatnotChannel;
use App\Services\ProfitLossReport;
use App\Support\AdminModules;
use App\Support\BusinessTime;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Profit & Loss — what each show made after COGS and pay, by day, week,
 * month or year, broken down by streamer, channel, business, weekday and
 * time slot. Admin only: streamers never see cost or profit.
 */
class ProfitLoss extends Page
{
    use HasModuleAccess;

    protected static string $moduleSlug = 'reporting';
    protected static ?string $title = 'Profit & Loss';
    protected static ?string $slug = 'profit-loss';

    #[Url]
    public string $period = 'week';

    #[Url]
    public string $anchor = '';

    #[Url]
    public string $channel = '';

    #[Url]
    public string $business = '';

    #[Url]
    public string $streamer = '';

    #[Url]
    public string $breakdownBy = 'streamer';

    #[Url]
    public string $showFilter = 'all';

    public string $sort = 'date';

    public int $shown = 50;

    public function showMore(): void
    {
        $this->shown += 100;
    }

    /** @var Collection<int, array<string, mixed>>|null */
    private ?Collection $rowsMemo = null;

    protected static function passesModuleAccessCheck(): bool
    {
        return (bool) (auth()->user()?->isAdmin() || auth()->user()?->isOwner());
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return AdminModules::navigationGroupFor('reporting');
    }

    public static function getNavigationSort(): ?int { return 0; }
    public static function getNavigationIcon(): string|\BackedEnum|null { return 'heroicon-o-presentation-chart-line'; }
    public function getView(): string { return 'filament.pages.profit-loss'; }

    public function getSubheading(): ?string
    {
        return 'Earnings (Whatnot net + tips) − COGS − streamer pay, per show. Shows still missing a figure are marked Incomplete and may change.';
    }

    public function mount(): void
    {
        if (! in_array($this->period, ['day', 'week', 'month', 'year'], true)) {
            $this->period = 'week';
        }
    }

    /** @return array{0:Carbon,1:Carbon,2:string} */
    public function range(): array
    {
        try {
            $a = $this->anchor !== '' ? Carbon::parse($this->anchor) : BusinessTime::local()->startOfDay();
        } catch (\Throwable) {
            $a = BusinessTime::local()->startOfDay();
        }
        $a = Carbon::parse($a->toDateString());

        return match ($this->period) {
            'day' => [$a->copy(), $a->copy(), $a->format('l, M j, Y')],
            'month' => [$a->copy()->startOfMonth(), $a->copy()->endOfMonth()->startOfDay(), $a->format('F Y')],
            'year' => [$a->copy()->startOfYear(), $a->copy()->endOfYear()->startOfDay(), (string) $a->year],
            default => [$a->copy()->startOfWeek(), $a->copy()->endOfWeek()->startOfDay(), $a->copy()->startOfWeek()->format('M j').' – '.$a->copy()->endOfWeek()->format('M j, Y')],
        };
    }

    /** @return array{0:Carbon,1:Carbon} the same length of time just before */
    public function previousRange(): array
    {
        [$from] = $this->range();

        return match ($this->period) {
            'day' => [$from->copy()->subDay(), $from->copy()->subDay()],
            'month' => [$from->copy()->subMonthNoOverflow()->startOfMonth(), $from->copy()->subMonthNoOverflow()->endOfMonth()->startOfDay()],
            'year' => [$from->copy()->subYear()->startOfYear(), $from->copy()->subYear()->endOfYear()->startOfDay()],
            default => [$from->copy()->subWeek(), $from->copy()->subDay()],
        };
    }

    public function setView(string $view): void
    {
        $this->period = in_array($view, ['day', 'week', 'month', 'year'], true) ? $view : 'week';
        $this->rowsMemo = null;
    }

    public function shift(int $by): void
    {
        [$from] = $this->range();
        $by = max(-1, min(1, $by));
        $next = match ($this->period) {
            'day' => $from->copy()->addDays($by),
            'month' => $from->copy()->addMonthsNoOverflow($by),
            'year' => $from->copy()->addYears($by),
            default => $from->copy()->addWeeks($by),
        };
        $this->anchor = $next->toDateString();
        $this->rowsMemo = null;
    }

    public function today(): void
    {
        $this->anchor = '';
        $this->rowsMemo = null;
    }

    public function updated(): void
    {
        $this->rowsMemo = null;
    }

    private function filters(): array
    {
        return [
            'channel' => $this->channel !== '' ? (int) $this->channel : null,
            'business' => $this->business !== '' ? (int) $this->business : null,
            'streamer' => $this->streamer !== '' ? (int) $this->streamer : null,
        ];
    }

    public function rows(): Collection
    {
        if ($this->rowsMemo !== null) {
            return $this->rowsMemo;
        }
        [$from, $to] = $this->range();

        return $this->rowsMemo = app(ProfitLossReport::class)->rows($from, $to, $this->filters());
    }

    public function totals(): array
    {
        return app(ProfitLossReport::class)->totals($this->rows());
    }

    public function previousTotals(): array
    {
        [$from, $to] = $this->previousRange();
        $report = app(ProfitLossReport::class);

        return $report->totals($report->rows($from, $to, $this->filters()));
    }

    public function trend(): Collection
    {
        [$from, $to] = $this->range();

        return app(ProfitLossReport::class)->trend($this->rows(), $from, $to);
    }

    public function breakdown(): Collection
    {
        $by = in_array($this->breakdownBy, ['streamer', 'channel', 'business', 'weekday', 'slot'], true) ? $this->breakdownBy : 'streamer';

        return app(ProfitLossReport::class)->breakdown($this->rows(), $by);
    }

    /** How each streamer did compared with the period before. */
    public function streamerTrend(): Collection
    {
        [$from, $to] = $this->previousRange();
        $report = app(ProfitLossReport::class);
        $before = $report->breakdown($report->rows($from, $to, $this->filters()), 'streamer')->keyBy('label');

        return $report->breakdown($this->rows(), 'streamer')->map(fn ($row) => $row + [
            'prev_profit' => $before[$row['label']]['profit'] ?? null,
            'prev_shows' => $before[$row['label']]['shows'] ?? 0,
        ]);
    }

    public function showRows(): Collection
    {
        $rows = $this->rows();
        $rows = match ($this->showFilter) {
            'losing' => $rows->where('profit', '<', 0),
            'incomplete' => $rows->filter(fn ($r) => $r['incomplete'] !== []),
            'complete' => $rows->filter(fn ($r) => $r['incomplete'] === []),
            default => $rows,
        };

        $sorted = match ($this->sort) {
            'profit' => $rows->sortBy('profit'),
            'profit_desc' => $rows->sortByDesc('profit'),
            'sales' => $rows->sortByDesc('sales'),
            default => $rows->sortByDesc(fn ($r) => ($r['date']?->toDateString() ?? '').($r['start']?->format('H:i') ?? '')),
        };

        return $sorted->values();
    }

    public function setSort(string $sort): void
    {
        $this->sort = in_array($sort, ['date', 'profit', 'profit_desc', 'sales'], true) ? $sort : 'date';
    }

    public function options(): array
    {
        return [
            'channels' => WhatnotChannel::orderBy('name')->pluck('name', 'id')->all(),
            'businesses' => Business::ordered()->pluck('name', 'id')->all(),
            'streamers' => Streamer::query()->streamers()->orderBy('name')->pluck('name', 'id')->all(),
        ];
    }

    public function exportCsv(): StreamedResponse
    {
        abort_unless(static::canAccess(), 403);
        [$from, $to] = $this->range();
        $rows = $this->showRows();

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Date', 'Start', 'Show', 'Channel', 'Streamer', 'Business', 'Sales', 'Earnings', 'COGS', 'Pay', 'Profit', 'Margin %', 'Hours worked', 'Incomplete']);
            foreach ($rows as $r) {
                fputcsv($out, [
                    $r['date']?->toDateString(), $r['start']?->format('g:i A'), $r['title'], $r['channel'], $r['streamer'], $r['business'],
                    $r['sales'], $r['earnings'], $r['cogs'], $r['payouts'], $r['profit'], $r['margin_pct'], $r['hours'], implode('; ', $r['incomplete']),
                ]);
            }
            fclose($out);
        }, 'profit-loss-'.$from->toDateString().'-to-'.$to->toDateString().'.csv', ['Content-Type' => 'text/csv']);
    }
}
