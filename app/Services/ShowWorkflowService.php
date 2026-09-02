<?php

namespace App\Services;

use App\Models\Show;

class ShowWorkflowService
{
    /**
     * Present the real cross-module lifecycle of a show without adding another
     * persisted status column. The operational status is derived from the
     * records each team already owns: show, streamer report, fulfillment and
     * payout.
     *
     * @return array{key:string,label:string,description:string,tone:string,step:int,total:int,blockers:array<int,string>}
     */
    public function stateFor(Show $show): array
    {
        $show->loadMissing(['streamerLogEntry.streamer', 'fulfillmentUsers', 'payouts.batch']);

        $report = $show->streamerLogEntry;
        $payouts = $show->payouts;
        $blockers = [];

        if ($show->status === 'cancelled') {
            return $this->state('cancelled', 'Cancelled', 'No additional workflow is required.', 'gray', 0, $blockers);
        }

        if ($payouts->isNotEmpty() && $payouts->every(fn ($payout) => $payout->status === 'paid')) {
            return $this->state('paid', 'Paid', 'Payroll is complete for this show.', 'success', 7, $blockers);
        }

        if ($payouts->isNotEmpty()) {
            $batch = $payouts->first(fn ($payout) => $payout->batch)?->batch;
            $label = $batch
                ? match ($batch->status) {
                    'paid' => 'Paid',
                    'submitted_to_adp' => 'Submitted to ADP',
                    'finalized' => 'Payroll Finalized',
                    default => 'In Pay Run',
                }
                : 'Payroll Draft';

            return $this->state('payroll', $label, 'Show earnings are included in payroll.', 'primary', 6, $blockers);
        }

        if (! $report) {
            if ($show->show_date?->isFuture()) {
                return $this->state('scheduled', 'Scheduled', 'Show has not ended yet.', 'gray', 1, $blockers);
            }

            $blockers[] = 'End of Stream report has not been started.';
            return $this->state('streamer_log', 'Needs Streamer Log', 'Streamer needs to record the post-show inventory and activity.', 'warning', 2, $blockers);
        }

        if ($report->status === 'changes_requested') {
            $blockers[] = 'Admin requested changes to the streamer report.';
            return $this->state('streamer_log', 'Changes Requested', 'Streamer needs to update and resubmit the report.', 'danger', 2, $blockers);
        }

        if (! $report->submitted_at) {
            $blockers[] = 'Streamer report is still a draft.';
            return $this->state('streamer_log', 'Streamer Log Draft', 'End of Stream report has been started but not submitted.', 'warning', 2, $blockers);
        }

        if ($report->status !== 'admin_approved' && $report->approval_status !== 'approved') {
            $blockers[] = 'Streamer report is waiting for admin approval.';
            return $this->state('admin_review', 'Admin Review', 'Review the streamer submission and inventory exceptions.', 'info', 3, $blockers);
        }

        if ($report->needsFulfillmentReview()) {
            $blockers[] = 'Fulfillment has not verified label / PWE activity.';
            return $this->state('fulfillment', 'Fulfillment Review', 'Fulfillment needs to verify the activity used for compensation.', 'purple', 4, $blockers);
        }

        $pnl = $show->profitAndLoss();
        if ((float) $show->gross_revenue <= 0 && (float) $show->whatnot_net <= 0) {
            $blockers[] = 'Show sales / settlement data is missing.';
        }
        if (! $show->latestDeductionRequest && (float) ($pnl['cogs'] ?? 0) <= 0) {
            $blockers[] = 'COGS has not been finalized.';
        }

        if ($blockers !== []) {
            return $this->state('payroll_review', 'Payroll Review', 'Post-show approvals are complete, but payroll inputs still need attention.', 'warning', 5, $blockers);
        }

        return $this->state('payroll_ready', 'Payroll Ready', 'The show is approved and ready to be included in the weekly pay run.', 'success', 5, $blockers);
    }

    /** @return array<int,array{key:string,label:string}> */
    public function steps(): array
    {
        return [
            ['key' => 'scheduled', 'label' => 'Show'],
            ['key' => 'streamer_log', 'label' => 'Streamer Log'],
            ['key' => 'admin_review', 'label' => 'Admin Review'],
            ['key' => 'fulfillment', 'label' => 'Fulfillment'],
            ['key' => 'payroll_ready', 'label' => 'Payroll Ready'],
            ['key' => 'payroll', 'label' => 'Pay Run'],
            ['key' => 'paid', 'label' => 'Paid'],
        ];
    }

    private function state(string $key, string $label, string $description, string $tone, int $step, array $blockers): array
    {
        return compact('key', 'label', 'description', 'tone', 'step', 'blockers') + ['total' => 7];
    }
}
