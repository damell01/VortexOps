<?php

namespace App\Filament\Pages;

use App\Models\FulfillmentRun;
use App\Models\User;
use App\Services\FulfillmentFlagService;
use App\Support\BusinessTime;
use Carbon\CarbonImmutable;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Fulfillment reports: what was packed, by whom, how fast, and each show's
 * completion record. The packer summary and show records replace the paper
 * tracking sheets the team kept for every show (the "pay sheets"): they are
 * built automatically from Start / Mark Packed / Finish and export to CSV.
 *
 * Packers see only their own numbers; managers see the whole team.
 */
class FulfillmentReports extends Page
{
    protected static ?string $title = 'Fulfillment Reports';
    protected static ?string $slug = 'fulfillment-reports';
    protected static bool $shouldRegisterNavigation = false;

    public const PERIODS = ['today' => 'Today', '7' => '7 days', '30' => '30 days', 'custom' => 'Custom'];

    #[Url(as: 'period')]
    public string $period = '7';

    #[Url(as: 'from')]
    public ?string $from = null;

    #[Url(as: 'until')]
    public ?string $until = null;

    public int $showPage = 1;

    public static function canAccess(): bool
    {
        $user = auth()->user();
        return (bool) ($user && ($user->isOwner() || $user->isAdmin()
            || $user->isFulfillmentAdmin() || $user->isFulfillment()));
    }

    public function getView(): string
    {
        return 'filament.pages.fulfillment-reports';
    }

    /** The page draws its own header. */
    public function getHeading(): string { return ''; }

    public function isManager(): bool
    {
        $user = auth()->user();
        return (bool) ($user && ($user->isOwner() || $user->isAdmin() || $user->isFulfillmentAdmin()));
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['period', 'from', 'until'], true)) {
            if (! array_key_exists($this->period, self::PERIODS)) $this->period = '7';
            $this->showPage = 1;
            unset($this->range, $this->packages, $this->runs);
        }
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} local start/end of day */
    #[Computed]
    public function range(): array
    {
        $tz = BusinessTime::zone();
        $today = CarbonImmutable::now($tz)->startOfDay();
        if ($this->period === 'custom') {
            $from = $this->parseDate($this->from) ?? $today->subDays(6);
            $until = $this->parseDate($this->until) ?? $today;
            if ($until->lessThan($from)) [$from, $until] = [$until, $from];
            if ($from->diffInDays($until) > 366) $from = $until->subDays(366);
            return [$from, $until->endOfDay()];
        }
        $days = $this->period === 'today' ? 1 : (int) $this->period;

        return [$today->subDays($days - 1), $today->endOfDay()];
    }

    private function parseDate(?string $value): ?CarbonImmutable
    {
        if (! $value || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) return null;
        try {
            return CarbonImmutable::createFromFormat('Y-m-d', $value, BusinessTime::zone())->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    private function utc(): array
    {
        [$from, $until] = $this->range;
        return [$from->utc(), $until->utc()];
    }

    /** Every package packed in the period (the member's own, for a member). */
    #[Computed]
    public function packages(): Collection
    {
        [$from, $until] = $this->utc();
        return DB::table('fulfillment_run_packages')->where('status', 'packed')->whereBetween('packed_at', [$from, $until])
            ->when(! $this->isManager(), fn ($q) => $q->where('packed_by', auth()->id()))
            ->get(['run_id', 'packed_by', 'packed_at']);
    }

    /** Shows finished in the period, with what the report needs. */
    #[Computed]
    public function runs(): Collection
    {
        [$from, $until] = $this->utc();
        return FulfillmentRun::query()
            ->whereIn('status', [FulfillmentRun::COMPLETED, FulfillmentRun::APPROVED])
            ->whereBetween('finished_at', [$from, $until])
            ->when(! $this->isManager(), fn ($q) => $q->whereHas('packers', fn ($p) => $p->where('users.id', auth()->id())))
            ->with(['show:id,title,show_date,whatnot_channel_id', 'show.channel:id,name', 'station:id,name', 'packers:id,name', 'approver:id,name'])
            ->withCount(['flags', 'flags as open_flags_count' => fn ($q) => $q->where('status', 'open')])
            ->orderByDesc('finished_at')->get();
    }

    #[Computed]
    public function kpis(): array
    {
        [$from, $until] = $this->utc();
        $buyers = DB::table('fulfillment_run_buyers')->where('status', 'packed')->whereBetween('packed_at', [$from, $until])
            ->when(! $this->isManager(), fn ($q) => $q->where('packed_by', auth()->id()))->count();
        $activeHours = $this->runs->sum(fn (FulfillmentRun $r) => $r->activeSeconds()) / 3600;
        $flags = DB::table('fulfillment_flags')->whereBetween('created_at', [$from, $until])
            ->when(! $this->isManager(), fn ($q) => $q->where('reported_by', auth()->id()));

        return [
            'packages' => $this->packages->count(),
            'buyers' => $buyers,
            'shows' => $this->runs->count(),
            'avg_minutes' => $this->runs->count() ? (int) round($this->runs->avg(fn ($r) => $r->activeSeconds()) / 60) : null,
            'per_hour' => $activeHours > 0 ? round($this->runs->sum('completed_packages') / $activeHours, 1) : null,
            'issues' => (clone $flags)->count(),
            'issues_open' => (clone $flags)->where('status', 'open')->count(),
            'awaiting' => FulfillmentRun::query()->where('status', FulfillmentRun::COMPLETED)
                ->when(! $this->isManager(), fn ($q) => $q->whereHas('packers', fn ($p) => $p->where('users.id', auth()->id())))->count(),
        ];
    }

    /**
     * Current operational snapshot, separate from the selected historical period.
     * Overdue is defined as a ready run older than 24 hours; no SLA is implied.
     * Only managers may see team-wide workload and station availability.
     */
    #[Computed]
    public function operations(): array
    {
        if (! $this->isManager()) {
            return [];
        }

        $backlog = FulfillmentRun::query()->whereIn('status', FulfillmentRun::OPEN)
            ->selectRaw('COUNT(*) as runs_count')
            ->selectRaw('COALESCE(SUM(CASE WHEN COALESCE(expected_packages, 0) > COALESCE(completed_packages, 0) THEN COALESCE(expected_packages, 0) - COALESCE(completed_packages, 0) ELSE 0 END), 0) as packages_remaining')
            ->selectRaw('SUM(CASE WHEN status = ? AND ready_at < ? THEN 1 ELSE 0 END) as overdue_ready', [
                FulfillmentRun::READY, now()->subDay(),
            ])->first();

        $stations = DB::table('fulfillment_stations')->where('active', true)->count();
        $occupied = FulfillmentRun::query()->whereIn('status', FulfillmentRun::ON_STATION)
            ->whereNotNull('station_occupied_id')->distinct()->count('station_occupied_id');

        return [
            'open_shows' => (int) ($backlog->runs_count ?? 0),
            'packages_remaining' => (int) ($backlog->packages_remaining ?? 0),
            'overdue_ready' => (int) ($backlog->overdue_ready ?? 0),
            'stations' => $stations,
            'occupied' => min($stations, $occupied),
            'utilization' => $stations > 0 ? round(min($stations, $occupied) / $stations * 100) : null,
        ];
    }

    /** Packages per local day, every day of the period present (zero days included). */
    #[Computed]
    public function daily(): array
    {
        [$from, $until] = $this->range;
        $tz = BusinessTime::zone();
        $counts = $this->packages->countBy(fn ($p) => CarbonImmutable::parse($p->packed_at, 'UTC')->setTimezone($tz)->toDateString());
        $days = [];
        for ($d = $from; $d->lessThanOrEqualTo($until); $d = $d->addDay()) {
            $days[] = ['date' => $d->toDateString(), 'label' => $d->format('M j'), 'short' => $d->format('D'), 'count' => (int) ($counts[$d->toDateString()] ?? 0)];
            if (count($days) > 120) break;
        }
        return $days;
    }

    /**
     * The digital pay sheet: per packer, what they packed and how long they were
     * on station. Hours are the active packing time of each finished show they
     * worked (pauses excluded); packages and buyers go to whoever marked them.
     */
    #[Computed]
    public function packers(): Collection
    {
        [$from, $until] = $this->utc();
        $pk = $this->packages->groupBy('packed_by');
        $buyers = DB::table('fulfillment_run_buyers')->where('status', 'packed')->whereBetween('packed_at', [$from, $until])
            ->select('packed_by', DB::raw('COUNT(*) as n'))->groupBy('packed_by')->pluck('n', 'packed_by');
        $flags = DB::table('fulfillment_flags')->whereBetween('created_at', [$from, $until])
            ->select('reported_by', DB::raw('COUNT(*) as n'))->groupBy('reported_by')->pluck('n', 'reported_by');
        $hours = [];
        $shows = [];
        foreach ($this->runs as $run) {
            foreach ($run->packers as $p) {
                $hours[$p->id] = ($hours[$p->id] ?? 0) + $run->activeSeconds() / 3600;
                $shows[$p->id][$run->id] = true;
            }
        }
        foreach ($pk as $uid => $rows) foreach ($rows as $row) $shows[$uid][$row->run_id] = true;
        $ids = collect(array_keys($shows))->merge($pk->keys())->merge($buyers->keys())->filter()->unique()
            ->when(! $this->isManager(), fn ($c) => $c->filter(fn ($id) => (int) $id === (int) auth()->id()));
        $users = User::query()->whereIn('id', $ids)->get(['id', 'name'])->keyBy('id');

        return $ids->map(fn ($id) => [
            'user' => $users[$id] ?? null,
            'shows' => count($shows[$id] ?? []),
            'packages' => isset($pk[$id]) ? $pk[$id]->count() : 0,
            'buyers' => (int) ($buyers[$id] ?? 0),
            'hours' => round($hours[$id] ?? 0, 2),
            'per_hour' => ($hours[$id] ?? 0) > 0 && isset($pk[$id]) ? round($pk[$id]->count() / $hours[$id], 1) : null,
            'issues' => (int) ($flags[$id] ?? 0),
        ])->filter(fn ($r) => $r['user'])->sortByDesc('packages')->values();
    }

    /** Packages each packer marked on each finished show, for the show records. */
    #[Computed]
    public function splits(): array
    {
        $rows = DB::table('fulfillment_run_packages')->whereIn('run_id', $this->runs->pluck('id'))->where('status', 'packed')
            ->select('run_id', 'packed_by', DB::raw('COUNT(*) as n'))->groupBy('run_id', 'packed_by')->get();
        $names = User::query()->whereIn('id', $rows->pluck('packed_by')->filter()->unique())->pluck('name', 'id');
        $out = [];
        foreach ($rows as $r) $out[$r->run_id][] = ['name' => $names[$r->packed_by] ?? 'Unknown', 'n' => (int) $r->n];
        return $out;
    }

    #[Computed]
    public function issueTypes(): Collection
    {
        [$from, $until] = $this->utc();
        return DB::table('fulfillment_flags')->whereBetween('created_at', [$from, $until])
            ->when(! $this->isManager(), fn ($q) => $q->where('reported_by', auth()->id()))
            ->select('type', DB::raw('COUNT(*) as n'), DB::raw("SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) as open"))
            ->groupBy('type')->orderByDesc('n')->get()
            ->map(fn ($r) => ['label' => FulfillmentFlagService::label($r->type), 'n' => (int) $r->n, 'open' => (int) $r->open]);
    }

    /** The latest issues in the period, under the by-type bars. */
    #[Computed]
    public function recentIssues(): Collection
    {
        [$from, $until] = $this->utc();
        return \App\Models\FulfillmentFlag::query()->whereBetween('created_at', [$from, $until])
            ->when(! $this->isManager(), fn ($q) => $q->where('reported_by', auth()->id()))
            ->with(['show:id,title', 'buyer:id,letter,buyer_username', 'reporter:id,name'])
            ->latest('id')->limit(4)->get();
    }

    public const SHOWS_PER_PAGE = 8;

    public function showSlice(): Collection
    {
        $pages = max(1, (int) ceil($this->runs->count() / self::SHOWS_PER_PAGE));
        $this->showPage = max(1, min($this->showPage, $pages));
        return $this->runs->forPage($this->showPage, self::SHOWS_PER_PAGE)->values();
    }

    public function setShowPage(int $page): void
    {
        $this->showPage = max(1, $page);
    }

    public static function hours(int $seconds): string
    {
        $m = intdiv(max(0, $seconds), 60);
        return intdiv($m, 60) . 'h ' . str_pad((string) ($m % 60), 2, '0', STR_PAD_LEFT) . 'm';
    }

    private function fileStamp(): string
    {
        [$from, $until] = $this->range;
        return $from->format('Y-m-d') . '_to_' . $until->format('Y-m-d');
    }

    public function exportPackers(): StreamedResponse
    {
        abort_unless(static::canAccess(), 403);
        $rows = $this->packers;
        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Packer', 'Shows', 'Packages', 'Buyers', 'Hours on station', 'Packages per hour', 'Issues reported']);
            foreach ($rows as $r) fputcsv($out, [$r['user']->name, $r['shows'], $r['packages'], $r['buyers'], $r['hours'], $r['per_hour'] ?? '', $r['issues']]);
            fclose($out);
        }, 'fulfillment-packers_' . $this->fileStamp() . '.csv', ['Content-Type' => 'text/csv']);
    }

    public function exportShows(): StreamedResponse
    {
        abort_unless(static::canAccess(), 403);
        $runs = $this->runs;
        $splits = $this->splits;
        $tz = BusinessTime::zone();
        return response()->streamDownload(function () use ($runs, $splits, $tz) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Show', 'Stream date', 'Channel', 'Station', 'Packers', 'Packed by', 'Started', 'Finished', 'Active time', 'Packages', 'Expected', 'Buyers', 'Issues', 'Status', 'Signed off by']);
            foreach ($runs as $r) {
                fputcsv($out, [
                    $r->show?->title, $r->show?->show_date?->format('Y-m-d'), $r->show?->channel?->name, $r->station?->name,
                    $r->packers->pluck('name')->join(', '),
                    collect($splits[$r->id] ?? [])->map(fn ($s) => $s['name'] . ' ' . $s['n'])->join('; '),
                    $r->started_at?->timezone($tz)->format('Y-m-d H:i'), $r->finished_at?->timezone($tz)->format('Y-m-d H:i'),
                    self::hours($r->activeSeconds()), $r->completed_packages, $r->expected_packages, $r->buyer_count, $r->flags_count,
                    $r->status === FulfillmentRun::APPROVED ? 'Signed off' : 'Needs sign-off', $r->approver?->name,
                ]);
            }
            fclose($out);
        }, 'fulfillment-shows_' . $this->fileStamp() . '.csv', ['Content-Type' => 'text/csv']);
    }
}
