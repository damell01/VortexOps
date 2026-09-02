<?php

namespace App\Services;

use App\Models\Payout;
use App\Models\Show;
use App\Models\WeeklyPayoutBatch;
use Illuminate\Support\Carbon;

class PayRunReadinessService
{
    public function __construct(
        private readonly PayoutService $payouts,
        private readonly ShowWorkflowService $workflow,
    ) {}

    /**
     * Return every condition that should stop a weekly pay run from being
     * finalized. This layers the existing payout sign-off checks with the
     * cross-module show workflow and source-freshness checks.
     *
     * @return array<int,string>
     */
    public function problems(WeeklyPayoutBatch $batch): array
    {
        $problems = $this->payouts->signOffProblems($batch);

        $overlap = WeeklyPayoutBatch::overlapping(
            $batch->week_start->toDateString(),
            $batch->week_end->toDateString(),
            $batch->id,
        );
        if ($overlap) {
            $problems[] = 'This Pay Run overlaps Pay Run #' . $overlap->id
                . ' (' . $overlap->week_start->format('M j') . '–' . $overlap->week_end->format('M j, Y') . ').';
        }

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

            if ($show->show_date?->lt($batch->week_start) || $show->show_date?->gt($batch->week_end)) {
                $problems[] = $show->title . ' — show date is outside this Pay Run period.';
            }

            $state = $this->workflow->stateFor($show);
            foreach ($state['blockers'] ?? [] as $blocker) {
                $problems[] = $show->title . ' — ' . $blocker;
            }
        }

        foreach ($payouts as $payout) {
            $person = $payout->streamer?->name ?? "Team member #{$payout->streamer_id}";
            $show = $payout->show_id ? $shows->get($payout->show_id) : null;
            $showTitle = $payout->show_id ? ($show?->title ?? "Show #{$payout->show_id}") : 'Manual payout';

            if ($payout->calculated_payout === null) {
                $problems[] = "{$showTitle} — {$person} does not have a calculated payout amount.";
            }

            if (in_array($payout->payout_type, ['hourly', 'hybrid'], true)
                && $payout->show_id
                && (float) ($payout->hours_worked ?? 0) <= 0
                && (int) ($show?->show_duration ?? 0) <= 0) {
                $problems[] = "{$showTitle} — {$person} is paid using hours, but no confirmed show duration is available.";
            }

            if (in_array($payout->payout_type, ['profit_share', 'hybrid'], true)
                && $payout->show_id
                && $show?->streamerLogEntry?->product_cost === null) {
                $problems[] = "{$showTitle} — {$person}'s profit-based payout does not have confirmed product cost.";
            }

            if ($show && $this->sourceChangedAfterPayout($show, $payout)) {
                $problems[] = "{$showTitle} — {$person}'s payout is stale because show, report, inventory, or fulfillment data changed after it was calculated. Recalculate the Pay Run.";
            }
        }

        return array_values(array_unique($problems));
    }

    public function ready(WeeklyPayoutBatch $batch): bool
    {
        return $this->problems($batch) === [];
    }

    private function sourceChangedAfterPayout(Show $show, Payout $payout): bool
    {
        if (! $payout->updated_at) {
            return true;
        }

        $timestamps = collect([
            $show->updated_at,
            $show->streamerLogEntry?->updated_at,
            $show->latestDeductionRequest?->updated_at,
        ]);

        foreach ($show->streamerLogEntry?->items ?? collect() as $item) {
            $timestamps->push($item->updated_at);
        }

        foreach ($show->latestDeductionRequest?->lines ?? collect() as $line) {
            $timestamps->push($line->updated_at);
        }

        $latestSourceUpdate = $timestamps
            ->filter()
            ->map(fn ($value) => $value instanceof Carbon ? $value : Carbon::parse($value))
            ->sortDesc()
            ->first();

        return $latestSourceUpdate?->gt($payout->updated_at) ?? false;
    }
}
