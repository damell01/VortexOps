<?php

namespace App\Services;

use App\Models\DeductionRequestLine;
use App\Models\Show;

class ShowWorkflowService
{
    /**
     * Present the real cross-module lifecycle of a show without adding another
     * persisted status column. The operational status is derived from the
     * records each team already owns: show, streamer report, logged inventory,
     * fulfillment review and payout.
     *
     * @return array{key:string,label:string,description:string,tone:string,step:int,total:int,blockers:array<int,string>}
     */
    public function stateFor(Show $show): array
    {
        $show->loadMissing([
            'streamerLogEntry.streamer',
            'fulfillmentUsers',
            'payouts.batch',
            'latestDeductionRequest.lines.inventoryItem',
        ]);

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
            $batch = $payouts->first(fn ($payout) => $payout->batch !== null)?->batch;
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

        $deductionRequest = $show->latestDeductionRequest;
        $lines = $deductionRequest?->lines ?? collect();

        if ($deductionRequest && ! in_array($deductionRequest->status, ['approved', 'processed'], true)) {
            $blockers[] = 'Logged inventory / COGS is not approved yet.';
        }

        $unmappedLines = $lines->filter(fn ($line) => ! $line->inventory_item_id)->count();
        if ($unmappedLines > 0) {
            $blockers[] = $unmappedLines . ' logged inventory line(s) still need an inventory item.';
        }

        $pendingFulfillment = $lines->filter(fn ($line) => ! $line->isFulfillmentReviewed())->count();
        $notFulfilled = $lines->filter(fn ($line) => $line->fulfillmentStatus() === DeductionRequestLine::FULFILLMENT_NOT_FULFILLED)->count();

        if ($pendingFulfillment > 0) {
            $blockers[] = $pendingFulfillment . ' logged item line(s) still need fulfillment review.';
            return $this->state('fulfillment', 'Fulfillment In Progress', 'Fulfillment is reviewing the streamer-logged items for this show.', 'purple', 4, $blockers);
        }

        if ($notFulfilled > 0) {
            $blockers[] = $notFulfilled . ' logged item line(s) are marked not fulfilled.';
            return $this->state('fulfillment', 'Fulfillment Issues', 'Fulfillment review is complete, but one or more logged items were not fulfilled.', 'danger', 4, $blockers);
        }

        if ($report->needsFulfillmentReview()) {
            $blockers[] = 'Fulfillment has not verified label / PWE activity.';
            return $this->state('fulfillment', 'Fulfillment Review', 'Fulfillment needs to verify the activity used for compensation.', 'purple', 4, $blockers);
        }

        // Revenue fields may legitimately be zero. Only treat them as missing
        // when neither source has actually been populated.
        if ($show->getRawOriginal('gross_revenue') === null && $show->getRawOriginal('whatnot_net') === null) {
            $blockers[] = 'Show sales / settlement data is missing.';
        }

        // A legitimately zero-COGS show is valid once the inventory deduction
        // request has been approved/processed. Do not infer readiness from a
        // positive dollar amount.
        if (! $deductionRequest || ! in_array($deductionRequest->status, ['approved', 'processed'], true)) {
            $blockers[] = 'COGS / logged inventory has not been finalized.';
        }

        if ($blockers !== []) {
            return $this->state('payroll_review', 'Payroll Review', 'Post-show approvals are complete, but payroll inputs still need attention.', 'warning', 5, array_values(array_unique($blockers)));
        }

        return $this->state('payroll_ready', 'Payroll Ready', 'The show is approved, fulfillment-reviewed and ready for the weekly pay run.', 'success', 5, $blockers);
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
