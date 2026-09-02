<?php

namespace App\Services;

use App\Models\Payout;
use App\Models\Show;
use App\Models\WeeklyPayoutBatch;

class PayRunReadinessService
{
    public function __construct(
        private readonly PayoutService $payouts,
        private readonly ShowWorkflowService $workflow,
    ) {}

    /**
     * Return every condition that should stop a weekly pay run from being
     * finalized. This intentionally layers the existing payout sign-off checks
     * with the cross-module show workflow so a draft payout cannot hide an
     * unfinished streamer report, fulfillment review, inventory issue, or
     * payroll input.
     *
     * @return array<int,string>
     */
    public function problems(WeeklyPayoutBatch $batch): array
    {
        $problems = $this->payouts->signOffProblems($batch);

        $payouts = $batch->payouts()
            ->with('streamer')
            ->get();

        $showIds = $payouts->pluck('show_id')->filter()->unique()->values();

        $shows = Show::query()
            ->whereIn('id', $showIds)
            ->with([
                'streamers',
                'streamerLogEntry.streamer',
                'streamerLogEntry.items.inventoryItem',
                'fulfillmentUsers',
                'payouts.batch',
                'latestDeductionRequest.lines.inventoryItem',
            ])
            ->get()
            ->keyBy('id');

        foreach ($showIds as $showId) {
            /** @var Show|null $show */
            $show = $shows->get($showId);

            if (! $show) {
                $problems[] = "Show #{$showId} — show record could not be loaded.";
                continue;
            }

            $state = $this->workflow->stateFor($show);
            foreach ($state['blockers'] ?? [] as $blocker) {
                $problems[] = $show->title . ' — ' . $blocker;
            }
        }

        foreach ($payouts as $payout) {
            $person = $payout->streamer?->name ?? "Team member #{$payout->streamer_id}";
            $showTitle = $payout->show_id ? ($shows->get($payout->show_id)?->title ?? "Show #{$payout->show_id}") : 'Manual payout';

            if ($payout->calculated_payout === null) {
                $problems[] = "{$showTitle} — {$person} does not have a calculated payout amount.";
            }

            if (in_array($payout->payout_type, ['hourly', 'hybrid'], true)
                && $payout->show_id
                && (float) ($payout->hours_worked ?? 0) <= 0
                && (int) ($shows->get($payout->show_id)?->show_duration ?? 0) <= 0) {
                $problems[] = "{$showTitle} — {$person} is paid using hours, but no confirmed show duration is available.";
            }

            if (in_array($payout->payout_type, ['profit_share', 'hybrid'], true)
                && $payout->show_id
                && $shows->get($payout->show_id)?->streamerLogEntry?->product_cost === null) {
                $problems[] = "{$showTitle} — {$person}'s profit-based payout does not have confirmed product cost.";
            }
        }

        return array_values(array_unique($problems));
    }

    public function ready(WeeklyPayoutBatch $batch): bool
    {
        return $this->problems($batch) === [];
    }
}
