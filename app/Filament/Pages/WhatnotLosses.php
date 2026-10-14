<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HasModuleAccess;
use App\Filament\Concerns\SendsShowLossSummary;
use App\Models\Business;
use App\Models\Streamer;
use App\Services\ShowLossDigest;
use App\Support\AdminModules;
use App\Support\BusinessTime;
use App\Support\NotificationSchedule;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Whatnot Losses — shows whose estimated Whatnot earnings came out below $0
 * (fees and giveaways ate the sales), before any business cost or payroll.
 * The same rows as Profit & Loss; send the list as a summary from here.
 */
class WhatnotLosses extends Page
{
    use HasModuleAccess;
    use SendsShowLossSummary;

    protected static string $moduleSlug = 'reporting';
    protected static ?string $title = 'Whatnot Losses';
    protected static ?string $slug = 'whatnot-losses';
    protected static bool $shouldRegisterNavigation = false;

    public const RANGES = ['this_week' => 'This week', 'last_week' => 'Last week', 'last_7' => 'Last 7 days', 'last_30' => 'Last 30 days', 'this_month' => 'This month', 'last_month' => 'Last month', 'custom' => 'Custom range'];

    #[Url]
    public string $range = 'last_week';

    #[Url]
    public string $from = '';

    #[Url]
    public string $to = '';

    #[Url]
    public string $business = '';

    #[Url]
    public string $streamer = '';

    public string $sort = 'amount';

    public string $dir = 'asc';

    private ?Collection $rowsMemo = null;

    protected static function passesModuleAccessCheck(): bool
    {
        return (bool) (auth()->user()?->isAdmin() || auth()->user()?->isOwner());
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return AdminModules::navigationGroupFor('reporting');
    }

    public function getView(): string { return 'filament.pages.whatnot-losses'; }

    public function getHeading(): string { return ''; }

    public function mount(): void
    {
        // A link from a summary carries its own dates.
        if ($this->from !== '' && $this->to !== '' && ! request()->has('range')) {
            $this->range = 'custom';
        }
        if (! array_key_exists($this->range, self::RANGES)) {
            $this->range = 'last_week';
        }
    }

    /** @return array{0:Carbon,1:Carbon} */
    public function period(): array
    {
        $today = Carbon::parse(BusinessTime::local()->toDateString());

        return match ($this->range) {
            'this_week' => [$today->copy()->startOfWeek(), $today->copy()->endOfWeek()->startOfDay()],
            'last_7' => [$today->copy()->subDays(6), $today],
            'last_30' => [$today->copy()->subDays(29), $today],
            'this_month' => [$today->copy()->startOfMonth(), $today->copy()->endOfMonth()->startOfDay()],
            'last_month' => [$today->copy()->subMonthNoOverflow()->startOfMonth(), $today->copy()->subMonthNoOverflow()->endOfMonth()->startOfDay()],
            'custom' => $this->customPeriod($today),
            default => [$today->copy()->startOfWeek()->subWeek(), $today->copy()->startOfWeek()->subDay()],
        };
    }

    private function customPeriod(Carbon $today): array
    {
        try {
            $from = $this->from !== '' ? Carbon::parse($this->from) : $today->copy()->subDays(6);
            $to = $this->to !== '' ? Carbon::parse($this->to) : $today;
        } catch (\Throwable) {
            return [$today->copy()->subDays(6), $today];
        }

        return $from->lte($to) ? [$from, $to] : [$to, $from];
    }

    public function periodLabel(): string
    {
        [$from, $to] = $this->period();

        return NotificationSchedule::rangeLabel($from, $to);
    }

    public function updated(string $name): void
    {
        $this->rowsMemo = null;
        if ($name === 'range' && $this->range === 'custom' && $this->from === '') {
            [$from, $to] = $this->period();
            $this->from = $from->toDateString();
            $this->to = $to->toDateString();
        }
    }

    public function rows(): Collection
    {
        if ($this->rowsMemo !== null) {
            return $this->rowsMemo;
        }
        [$from, $to] = $this->period();
        $rows = app(ShowLossDigest::class)->rows('whatnot_negative', $from, $to, $this->business !== '' ? (int) $this->business : null);
        if ($this->streamer !== '') {
            $rows = $rows->where('streamer_id', (int) $this->streamer);
        }
        $rows = match ($this->sort) {
            'show' => $rows->sortBy('title', SORT_NATURAL | SORT_FLAG_CASE),
            'business' => $rows->sortBy('business'),
            'streamer' => $rows->sortBy('streamer'),
            'date' => $rows->sortBy(fn ($r) => $r['date']?->toDateString()),
            'synced' => $rows->sortBy(fn ($r) => $r['synced_at']?->timestamp ?? 0),
            default => $rows->sortBy('whatnot_net'),
        };
        if ($this->dir === 'desc') {
            $rows = $rows->reverse();
        }

        return $this->rowsMemo = $rows->values();
    }

    public function sortBy(string $column): void
    {
        $this->dir = $this->sort === $column && $this->dir === 'asc' ? 'desc' : 'asc';
        $this->sort = $column;
        $this->rowsMemo = null;
    }

    public function totals(): array
    {
        $rows = $this->rows();

        return [
            'count' => $rows->count(),
            'total' => round((float) $rows->sum('whatnot_net'), 2),
            'businesses' => $rows->pluck('business')->unique()->count(),
        ];
    }

    public function options(): array
    {
        return [
            'businesses' => Business::ordered()->pluck('name', 'id')->all(),
            'streamers' => Streamer::query()->streamers()->orderBy('name')->pluck('name', 'id')->all(),
        ];
    }

    public function lossEvent(): string
    {
        return 'whatnot_negative';
    }

    public function lossDigest(): array
    {
        [$from, $to] = $this->period();
        $rows = $this->rows();
        $digest = app(ShowLossDigest::class)->build('whatnot_negative', $from, $to, $this->business !== '' ? (int) $this->business : null);
        // Respect the streamer filter on screen too.
        if ($this->streamer !== '') {
            $ids = $rows->pluck('id')->all();
            $digest['shows'] = array_values(array_filter($digest['shows'], fn ($s) => in_array($s['id'], $ids, true)));
            $digest['count'] = count($digest['shows']);
            $digest['total'] = round(array_sum(array_column($digest['shows'], 'amount')), 2);
        }

        return $digest;
    }

    public function exportCsv(): StreamedResponse
    {
        abort_unless(static::canAccess(), 403);
        [$from, $to] = $this->period();
        $rows = $this->rows();

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Show', 'Business', 'Streamer', 'Channel', 'Show date', 'Estimated Whatnot earnings', 'Gross sales', 'Last synced']);
            foreach ($rows as $r) {
                fputcsv($out, [$r['title'], $r['business'], $r['streamer'], $r['channel'], $r['date']?->toDateString(), $r['whatnot_net'], $r['sales'], $r['synced_at']?->toDateTimeString()]);
            }
            fclose($out);
        }, 'whatnot-losses-'.$from->toDateString().'-to-'.$to->toDateString().'.csv', ['Content-Type' => 'text/csv']);
    }
}
