<?php

namespace App\Filament\Pages;

use App\Filament\Resources\FulfillmentResource;
use App\Filament\Resources\ShowResource;
use App\Filament\Resources\StreamerLogResource;
use App\Filament\Resources\WeeklyPayoutBatchResource;
use App\Models\Payout;
use App\Models\Show;
use App\Models\Streamer;
use App\Models\WeeklyPayoutBatch;
use App\Services\PayRunReadinessService;
use App\Services\ShowWorkflowService;
use BackedEnum;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use UnitEnum;

class PayrollOverview extends Page
{
    protected static ?string $title = 'Payroll Dashboard';
    protected static ?string $navigationLabel = 'Payroll';
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';
    protected static string|UnitEnum|null $navigationGroup = 'Payouts';
    protected static ?int $navigationSort = 1;

    public function getView(): string
    {
        return 'filament.pages.payroll-overview';
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public function getSubheading(): ?string
    {
        return 'Current weekly payroll, show-by-show calculations, blockers and resolution actions in one place.';
    }

    public function currentPayRun(): ?WeeklyPayoutBatch
    {
        $weekStart = now()->startOfWeek()->toDateString();
        $weekEnd = now()->endOfWeek()->toDateString();

        return WeeklyPayoutBatch::query()
            ->withCount('payouts')
            ->whereDate('week_start', '<=', $weekEnd)
            ->whereDate('week_end', '>=', $weekStart)
            ->latest('week_start')
            ->first();
    }

    public function needsAttention(): array
    {
        $warnings = [];
        $run = $this->currentPayRun();

        $membersMissingStructure = Streamer::query()
            ->where('status', 'active')
            ->get()
            ->filter(function (Streamer $member): bool {
                try {
                    $comp = $member->effectiveCompensation();
                    return blank($comp['structure'] ?? null);
                } catch (\Throwable) {
                    return true;
                }
            })
            ->count();

        if ($membersMissingStructure > 0) {
            $warnings[] = $membersMissingStructure . ' active team member(s) need a payment structure reviewed.';
        }

        foreach ($this->allCurrentWeekShows() as $show) {
            foreach ($show->getAttribute('workflow_state')['blockers'] ?? [] as $blocker) {
                $warnings[] = $show->title . ': ' . $blocker;
            }
            foreach ($show->getAttribute('payrun_problems') ?? [] as $problem) {
                $warnings[] = $problem;
            }
        }

        if (! $run) {
            $warnings[] = 'No pay run exists for the current week. Create it after the shows you intend to pay are payroll-ready.';
            return array_values(array_unique($warnings));
        }

        if ($run->status === 'draft') {
            foreach (app(PayRunReadinessService::class)->problems($run) as $problem) {
                $warnings[] = $problem;
            }
        }

        return array_values(array_unique($warnings));
    }

    public function currentBreakdown(): array
    {
        $run = $this->currentPayRun();
        if (! $run) {
            return ['people' => 0, 'streamers' => 0, 'fulfillment' => 0, 'streamer_total' => 0.0, 'fulfillment_total' => 0.0];
        }

        $payouts = Payout::query()
            ->where('weekly_payout_batch_id', $run->id)
            ->with('streamer:id,member_type')
            ->get();

        $people = $payouts->pluck('streamer_id')->filter()->unique();
        $streamerIds = $payouts->filter(fn (Payout $p) => ! $p->streamer?->isFulfillment())->pluck('streamer_id')->filter()->unique();
        $fulfillmentIds = $payouts->filter(fn (Payout $p) => $p->streamer?->isFulfillment())->pluck('streamer_id')->filter()->unique();

        return [
            'people' => $people->count(),
            'streamers' => $streamerIds->count(),
            'fulfillment' => $fulfillmentIds->count(),
            'streamer_total' => (float) $payouts->filter(fn (Payout $p) => ! $p->streamer?->isFulfillment())->sum('calculated_payout'),
            'fulfillment_total' => (float) $payouts->filter(fn (Payout $p) => $p->streamer?->isFulfillment())->sum('calculated_payout'),
        ];
    }

    public function currentWeekShows(): Collection
    {
        $shows = $this->allCurrentWeekShows();
        $filter = request()->string('workflow')->toString();

        if ($filter === '' || $filter === 'all') {
            return $shows;
        }

        return $shows->filter(function (Show $show) use ($filter): bool {
            $key = $show->getAttribute('workflow_state')['key'] ?? '';
            $hasPayRunProblems = ($show->getAttribute('payrun_problems') ?? []) !== [];

            return match ($filter) {
                'blocked' => $hasPayRunProblems || ! in_array($key, ['payroll_ready', 'payroll', 'paid'], true),
                'ready' => ! $hasPayRunProblems && $key === 'payroll_ready',
                'in_run' => ! $hasPayRunProblems && $key === 'payroll',
                'paid' => $key === 'paid',
                default => $key === $filter,
            };
        })->values();
    }

    public function workflowBreakdown(): array
    {
        $shows = $this->allCurrentWeekShows();

        return [
            'all' => $shows->count(),
            'blocked' => $shows->filter(function (Show $show): bool {
                $key = $show->getAttribute('workflow_state')['key'] ?? '';
                return ($show->getAttribute('payrun_problems') ?? []) !== []
                    || ! in_array($key, ['payroll_ready', 'payroll', 'paid'], true);
            })->count(),
            'ready' => $shows->filter(fn (Show $show) => ($show->getAttribute('payrun_problems') ?? []) === []
                && ($show->getAttribute('workflow_state')['key'] ?? '') === 'payroll_ready')->count(),
            'in_run' => $shows->filter(fn (Show $show) => ($show->getAttribute('payrun_problems') ?? []) === []
                && ($show->getAttribute('workflow_state')['key'] ?? '') === 'payroll')->count(),
            'paid' => $shows->filter(fn (Show $show) => ($show->getAttribute('workflow_state')['key'] ?? '') === 'paid')->count(),
        ];
    }

    /** @return array{label:string,url:string,tone:string} */
    public function showResolution(Show $show): array
    {
        $state = $show->getAttribute('workflow_state');
        $key = $state['key'] ?? '';
        $log = $show->streamerLogEntry;
        $payRunProblems = $show->getAttribute('payrun_problems') ?? [];

        if ($payRunProblems !== [] && $this->currentPayRun()) {
            return [
                'label' => 'Recalculate Run',
                'url' => WeeklyPayoutBatchResource::getUrl('view', ['record' => $this->currentPayRun()]),
                'tone' => 'warning',
            ];
        }

        if (in_array($key, ['streamer_log', 'admin_review'], true) && $log) {
            return [
                'label' => $key === 'admin_review' ? 'Review Log' : 'Open Log',
                'url' => StreamerLogResource::getUrl('edit', ['record' => $log]),
                'tone' => 'warning',
            ];
        }

        if ($key === 'fulfillment') {
            return [
                'label' => 'Resolve Fulfillment',
                'url' => FulfillmentResource::getUrl('view', ['record' => $show]),
                'tone' => 'primary',
            ];
        }

        if ($key === 'payroll' && $show->payouts->first(fn (Payout $p) => $p->batch)?->batch) {
            $batch = $show->payouts->first(fn (Payout $p) => $p->batch)?->batch;
            return [
                'label' => 'Open Pay Run',
                'url' => WeeklyPayoutBatchResource::getUrl('view', ['record' => $batch]),
                'tone' => 'primary',
            ];
        }

        if ($key === 'payroll_ready' && $this->currentPayRun()) {
            return [
                'label' => 'Review Pay Run',
                'url' => WeeklyPayoutBatchResource::getUrl('view', ['record' => $this->currentPayRun()]),
                'tone' => 'success',
            ];
        }

        return [
            'label' => in_array($key, ['payroll_review'], true) ? 'Fix Show Inputs' : 'Open Show',
            'url' => ShowResource::getUrl('view', ['record' => $show]),
            'tone' => $key === 'payroll_review' ? 'warning' : 'gray',
        ];
    }

    public function readinessSummary(): array
    {
        $shows = $this->allCurrentWeekShows();
        return [
            'shows' => $shows->count(),
            'ready' => $shows->filter(function (Show $show): bool {
                $key = $show->getAttribute('workflow_state')['key'] ?? '';
                return ($show->getAttribute('payrun_problems') ?? []) === []
                    && in_array($key, ['payroll_ready', 'payroll', 'paid'], true);
            })->count(),
            'review' => $shows->filter(function (Show $show): bool {
                $key = $show->getAttribute('workflow_state')['key'] ?? '';
                return ($show->getAttribute('payrun_problems') ?? []) !== []
                    || ! in_array($key, ['payroll_ready', 'payroll', 'paid'], true);
            })->count(),
            'show_payroll' => (float) $shows->sum(fn (Show $show) => (float) ($show->getAttribute('pnl_summary')['payouts'] ?? 0)),
        ];
    }

    public function recentPayRuns(): Collection
    {
        return WeeklyPayoutBatch::query()
            ->withCount('payouts')
            ->latest('week_start')
            ->limit(6)
            ->get();
    }

    private function allCurrentWeekShows(): Collection
    {
        $run = $this->currentPayRun();
        $start = $run?->week_start ?? now()->startOfWeek();
        $end = $run?->week_end ?? now()->endOfWeek();
        $workflow = app(ShowWorkflowService::class);
        $payRunProblems = $run && $run->status === 'draft'
            ? app(PayRunReadinessService::class)->problems($run)
            : [];

        return Show::query()
            ->inChannelContext()
            ->whereBetween('show_date', [$start->toDateString(), $end->toDateString()])
            ->whereNotIn('status', ['cancelled'])
            ->with([
                'streamers',
                'streamerLogEntry.streamer',
                'streamerLogEntry.items.inventoryItem',
                'fulfillmentUsers',
                'payouts.batch',
                'latestDeductionRequest.lines.inventoryItem',
            ])
            ->withSum('payouts', 'calculated_payout')
            ->orderByDesc('show_date')
            ->get()
            ->map(function (Show $show) use ($workflow, $payRunProblems) {
                $show->setAttribute('workflow_state', $workflow->stateFor($show));
                $show->setAttribute('pnl_summary', $show->profitAndLoss());

                $prefix = ($show->title ?: "Show #{$show->id}") . ' — ';
                $show->setAttribute('payrun_problems', array_values(array_filter(
                    $payRunProblems,
                    fn (string $problem) => str_starts_with($problem, $prefix)
                )));

                return $show;
            });
    }
}
