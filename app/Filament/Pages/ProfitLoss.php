<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HasModuleAccess;
use App\Filament\Concerns\SendsShowLossSummary;
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
    use SendsShowLossSummary;

    protected static string $moduleSlug = 'reporting';
    protected static ?string $title = 'Business Profitability';
    protected static ?string $slug = 'profit-loss';

    #[Url]
    public string $period = 'week';

    #[Url]
    public string $anchor = '';

    #[Url]
    public string $from = '';

    #[Url]
    public string $to = '';

    #[Url]
    public string $channel = '';

    #[Url]
    public string $business = '';

    #[Url]
    public string $streamer = '';

    #[Url]
    public string $breakdownBy = 'streamer';

    /** all | finalized | incomplete | losses (finalized, lost money) | whatnot (negative Whatnot earnings) | losing (any loss) */
    #[Url(as: 'tab')]
    public string $showFilter = 'all';

    /** The show open in the detail panel. */
    public ?int $selected = null;

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

    public function getHeading(): string { return ''; }

    public const TABS = ['all' => 'All Shows', 'finalized' => 'Finalized', 'incomplete' => 'Incomplete', 'losses' => 'Business Losses', 'whatnot' => 'Whatnot Losses'];

    public function mount(): void
    {
        if (! in_array($this->period, ['day', 'week', 'month', 'year', 'custom'], true)) {
            $this->period = 'week';
        }
        if ($this->showFilter === 'complete') {
            $this->showFilter = 'finalized';
        }
    }

    /** @return array{0:Carbon,1:Carbon,2:string} */
    public function range(): array
    {
        if ($this->period === 'custom' && $this->from !== '' && $this->to !== '') {
            try {
                $from = Carbon::createFromFormat('!Y-m-d', $this->from);
                $to = Carbon::createFromFormat('!Y-m-d', $this->to);
                if ($from && $to && $from->lte($to)) return [$from, $to, $from->format('M j, Y').' – '.$to->format('M j, Y')];
            } catch (\Throwable) {
                // Malformed URL dates fall back to the normal weekly range.
            }
        }
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
        [$from, $to] = $this->range();
        if ($this->period === 'custom') return [$from->copy()->subDays($from->diffInDays($to) + 1), $from->copy()->subDay()];

        return match ($this->period) {
            'day' => [$from->copy()->subDay(), $from->copy()->subDay()],
            'month' => [$from->copy()->subMonthNoOverflow()->startOfMonth(), $from->copy()->subMonthNoOverflow()->endOfMonth()->startOfDay()],
            'year' => [$from->copy()->subYear()->startOfYear(), $from->copy()->subYear()->endOfYear()->startOfDay()],
            default => [$from->copy()->subWeek(), $from->copy()->subDay()],
        };
    }

    public function setView(string $view): void
    {
        $this->period = in_array($view, ['day', 'week', 'month', 'year', 'custom'], true) ? $view : 'week';
        $this->rowsMemo = null;
    }

    public function shift(int $by): void
    {
        [$from, $to] = $this->range();
        $by = max(-1, min(1, $by));
        if ($this->period === 'custom') {
            $days = ($from->diffInDays($to) + 1) * $by;
            $this->from = $from->copy()->addDays($days)->toDateString();
            $this->to = $to->copy()->addDays($days)->toDateString();
            $this->rowsMemo = null;
            return;
        }
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
        if ($this->period === 'custom') $this->period = 'week';
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
            'losing' => $rows->filter(fn($r) => $r['incomplete'] === [] && ($r['pay_completed'] ?? false) && $r['profit'] < 0),
            'paid' => $rows->filter(fn($r)=>$r['incomplete'] === [] && ($r['pay_completed'] ?? false)),
            'pending' => $rows->filter(fn($r)=>$r['incomplete'] !== [] || !($r['pay_completed'] ?? false)),
            'incomplete' => $rows->filter(fn ($r) => $r['incomplete'] !== []),
            'complete', 'finalized' => $rows->filter(fn ($r) => $r['incomplete'] === []),
            'losses' => $rows->filter(fn ($r) => $r['incomplete'] === [] && $r['profit'] < 0),
            'whatnot' => $rows->where('whatnot_net', '<', 0),
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

    public function setTab(string $tab): void
    {
        $this->showFilter = array_key_exists($tab, self::TABS) ? $tab : 'all';
        $this->shown = 50;
    }

    /** Whatnot's estimated earnings by day (by month for a year); days still to come are greyed. */
    public function earningsChart(): array
    {
        [$from, $to] = $this->range();
        $today = BusinessTime::local()->toDateString();
        $year = $this->period === 'year';
        $buckets = [];
        for ($d = $from->copy(); $d->lte($to); $year ? $d->addMonthNoOverflow()->startOfMonth() : $d->addDay()) {
            $key = $year ? $d->format('Y-m') : $d->toDateString();
            $buckets[$key] = [
                'label' => $year ? $d->format('M') : ($this->period === 'week' ? $d->format('D')."\n".$d->format('M j') : $d->format('j')),
                'value' => 0.0, 'shows' => 0,
                'muted' => $year ? $d->copy()->startOfMonth()->toDateString() > $today : $d->toDateString() > $today,
            ];
        }
        foreach ($this->rows() as $r) {
            $key = $year ? $r['date']?->format('Y-m') : $r['date']?->toDateString();
            if ($key !== null && isset($buckets[$key])) {
                $buckets[$key]['value'] += $r['whatnot_net'];
                $buckets[$key]['shows']++;
            }
        }

        return array_values(array_map(fn ($b) => $b + ['title' => str_replace("\n", ' ', $b['label']).($b['muted'] ? ': no data yet' : ': $'.number_format($b['value'], 2).' · '.$b['shows'].' '.str('show')->plural($b['shows']))], $buckets));
    }

    /** What has to be done before this period's profit is final. */
    public function checklist(): array
    {
        $rows = $this->rows();
        [$from, $to] = $this->range();
        $flagged = \App\Models\TimeEntry::needsReview()->whereBetween('clocked_in_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])->count()
            + app(\App\Services\TimeTrackingService::class)->showHoursIssues(null, $rows->pluck('id')->all())->count();
        $unpaid = $rows->filter(fn ($r) => ! $r['pay_paid'])->count();
        $noCogs = $rows->filter(fn ($r) => ! $r['cogs_recorded'])->count();
        $noEarnings = $rows->filter(fn ($r) => in_array('Earnings not in yet', $r['incomplete'], true))->count();

        return [
            ['Whatnot earnings in', $noEarnings ? "{$noEarnings} ".str('show')->plural($noEarnings).' still waiting on Whatnot\'s earnings' : 'Every show has its earnings', $noEarnings === 0, null],
            ['Inventory costs logged', $noCogs ? "Add and approve COGS for {$noCogs} ".str('show')->plural($noCogs) : 'COGS approved for every show', $noCogs === 0, $noCogs ? 'incomplete' : null],
            ['Hours worked approved', $flagged ? "{$flagged} ".str('item')->plural($flagged).' to review in Time & Attendance' : 'Nothing waiting for review', $flagged === 0, $flagged ? 'time' : null],
            ['Pay runs paid', $unpaid ? "Record completed payouts for {$unpaid} ".str('show')->plural($unpaid) : 'Every show\'s pay is paid', $unpaid === 0, $unpaid ? 'payroll' : null],
        ];
    }

    public function select(int $id): void
    {
        $this->selected = $this->selected === $id ? null : $id;
    }

    /** The open show's numbers and the pay runs that paid it. */
    public function detail(): ?array
    {
        if ($this->selected === null) {
            return null;
        }
        $row = $this->rows()->firstWhere('id', $this->selected);
        if (! $row) {
            return null;
        }
        $payouts = \App\Models\Payout::query()->where('show_id', $row['id'])
            ->with(['batch:id,status,week_start,week_end,kind,pay_run_type_name', 'streamer:id,name'])->get();

        return $row + ['pay_runs' => $payouts->map(fn ($p) => [
            'who' => $p->streamer?->name ?? 'Payout',
            'amount' => (float) $p->calculated_payout,
            'run' => $p->batch ? $p->batch->kindLabel().' · '.$p->batch->week_start?->format('M j').' – '.$p->batch->week_end?->format('M j') : 'Not in a pay run yet',
            'run_id' => $p->batch?->id,
            'status' => $p->status === 'paid' || $p->batch?->status === 'paid' ? 'Paid' : ($p->batch ? ucfirst(str_replace('_', ' ', $p->batch->status)) : 'Draft'),
        ])->all()];
    }

    public function lossEvent(): string
    {
        return 'show_lost_money';
    }

    public function lossDigest(): array
    {
        [$from, $to] = $this->range();

        return app(\App\Services\ShowLossDigest::class)->build('show_lost_money', $from, $to, $this->business !== '' ? (int) $this->business : null);
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
