<?php

namespace App\Filament\Pages;

use App\Models\Payout;
use App\Models\Show;
use App\Models\Streamer;
use App\Models\WeeklyPayoutBatch;
use App\Services\PayoutService;
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
        return 'Current weekly payroll, show-by-show calculations, exceptions, rates and pay run history in one place.';
    }

    public function currentPayRun(): ?WeeklyPayoutBatch
    {
        $weekStart = now()->startOfWeek()->toDateString();
        $weekEnd = now()->endOfWeek()->toDateString();

        return WeeklyPayoutBatch::query()
            ->withCount('payouts')
            ->where(function ($query) use ($weekStart, $weekEnd) {
                $query->whereBetween('week_start', [$weekStart, $weekEnd])
                    ->orWhereBetween('week_end', [$weekStart, $weekEnd]);
            })
            ->latest('week_start')
            ->first()
            ?? WeeklyPayoutBatch::query()->withCount('payouts')->latest('week_start')->first();
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

        foreach ($this->currentWeekShows() as $show) {
            $state = $show->getAttribute('workflow_state');
            foreach ($state['blockers'] ?? [] as $blocker) {
                $warnings[] = $show->title . ': ' . $blocker;
            }
        }

        if (! $run) {
            $warnings[] = 'No pay run exists yet. Create the weekly run when payroll is ready for review.';
            return array_values(array_unique($warnings));
        }

        if ($run->status === 'draft') {
            foreach (app(PayoutService::class)->signOffProblems($run) as $problem) {
                $warnings[] = $problem;
            }

            $draftWithoutAmount = $run->payouts()
                ->where('status', 'draft')
                ->whereNull('calculated_payout')
                ->count();

            if ($draftWithoutAmount > 0) {
                $warnings[] = $draftWithoutAmount . ' payout entry/entries do not have a calculated amount yet.';
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
        $run = $this->currentPayRun();
        $start = $run?->week_start ?? now()->startOfWeek();
        $end = $run?->week_end ?? now()->endOfWeek();
        $workflow = app(ShowWorkflowService::class);

        return Show::query()
            ->inChannelContext()
            ->whereBetween('show_date', [$start->toDateString(), $end->toDateString()])
            ->whereNotIn('status', ['cancelled'])
            ->with([
                'streamers',
                'streamerLogEntry.streamer',
                'fulfillmentUsers',
                'payouts.batch',
                'latestDeductionRequest.lines',
            ])
            ->withSum('payouts', 'calculated_payout')
            ->orderByDesc('show_date')
            ->get()
            ->map(function (Show $show) use ($workflow) {
                $show->setAttribute('workflow_state', $workflow->stateFor($show));
                $show->setAttribute('pnl_summary', $show->profitAndLoss());
                return $show;
            });
    }

    public function readinessSummary(): array
    {
        $shows = $this->currentWeekShows();
        return [
            'shows' => $shows->count(),
            'ready' => $shows->filter(fn (Show $show) => in_array($show->getAttribute('workflow_state')['key'], ['payroll_ready', 'payroll', 'paid'], true))->count(),
            'review' => $shows->filter(fn (Show $show) => ! in_array($show->getAttribute('workflow_state')['key'], ['payroll_ready', 'payroll', 'paid'], true))->count(),
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
}
