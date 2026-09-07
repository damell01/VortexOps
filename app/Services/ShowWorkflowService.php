<?php

namespace App\Services;

use App\Models\FulfillmentPackage;
use App\Models\Show;
use App\Models\StreamerLogItem;

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
            'streamerLogEntry.items.inventoryItem',
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

        $batch = $payouts->first(fn ($payout) => $payout->batch !== null)?->batch;

        if ($batch && in_array($batch->status, ['finalized', 'submitted_to_adp', 'paid'], true)) {
            $label = match ($batch->status) {
                'paid' => 'Paid',
                'submitted_to_adp' => 'Submitted to ADP',
                default => 'Payroll Finalized',
            };

            return $this->state(
                $batch->status === 'paid' ? 'paid' : 'payroll',
                $label,
                'Show earnings have passed payroll sign-off.',
                $batch->status === 'paid' ? 'success' : 'primary',
                $batch->status === 'paid' ? 7 : 6,
                $blockers,
            );
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

        $isApproved = $report->status === 'admin_approved' || $report->approval_status === 'approved';
        if (! $isApproved) {
            $blockers[] = 'Streamer report is waiting for admin approval.';
            return $this->state('admin_review', 'Admin Review', 'Review the streamer submission and inventory exceptions.', 'info', 3, $blockers);
        }

        $loggedItems = $report->items;
        $pendingFulfillment = $loggedItems->filter(fn (StreamerLogItem $item) => ! $item->isFulfillmentReviewed())->count();
        $notFulfilled = $loggedItems->filter(fn (StreamerLogItem $item) => $item->fulfillmentStatus() === StreamerLogItem::FULFILLMENT_NOT_FULFILLED)->count();

        if ($pendingFulfillment > 0) {
            $blockers[] = $pendingFulfillment . ' streamer-logged item line(s) still need fulfillment review.';
            return $this->state('fulfillment', 'Fulfillment In Progress', 'Fulfillment is reviewing the items the streamer logged for this show.', 'purple', 4, $blockers);
        }

        if ($notFulfilled > 0) {
            $blockers[] = $notFulfilled . ' streamer-logged item line(s) are marked not fulfilled.';
            return $this->state('fulfillment', 'Fulfillment Issues', 'Fulfillment review is complete, but one or more logged items were not fulfilled.', 'danger', 4, $blockers);
        }

        $packages = FulfillmentPackage::query()->where('show_id', $show->id)->get(['id', 'status', 'sealed_at']);
        if ($loggedItems->isNotEmpty() && $packages->isEmpty()) {
            $blockers[] = 'No physical fulfillment box has been built for the logged items.';
            return $this->state('fulfillment', 'Build Boxes', 'Build at least one physical box before fulfillment can be completed.', 'purple', 4, $blockers);
        }

        $openBoxes = $packages->filter(fn (FulfillmentPackage $package) => ! $package->isSealed())->count();
        if ($openBoxes > 0) {
            $blockers[] = $openBoxes . ' fulfillment box(es) are still open.';
            return $this->state('fulfillment', 'Seal Boxes', 'All physical boxes must be verified and sealed before payroll can continue.', 'purple', 4, $blockers);
        }

        if ($report->fulfillment_reviewed_at === null) {
            $blockers[] = 'Fulfillment has not signed off on the completed packing work.';
            return $this->state('fulfillment', 'Ready to Complete', 'All units are accounted for and boxes are sealed. Fulfillment can complete the show.', 'purple', 4, $blockers);
        }

        $unmatchedLoggedItems = $loggedItems->filter(fn (StreamerLogItem $item) => ! $item->inventory_item_id)->count();
        if ($unmatchedLoggedItems > 0) {
            $blockers[] = $unmatchedLoggedItems . ' streamer-logged item line(s) are not matched to inventory.';
        }

        $deductionRequest = $show->latestDeductionRequest;
        if ($deductionRequest) {
            $unmappedCogsLines = $deductionRequest->lines->filter(fn ($line) => ! $line->inventory_item_id)->count();
            if ($unmappedCogsLines > 0) {
                $blockers[] = $unmappedCogsLines . ' COGS line(s) still need an inventory item.';
            }

            if (! in_array($deductionRequest->status, ['approved', 'processed'], true)) {
                $blockers[] = 'The COGS adjustment request has not been finalized.';
            }
        } elseif ($loggedItems->isNotEmpty() && $report->product_cost === null) {
            $blockers[] = 'Product cost has not been confirmed for the logged items.';
        }

        if ($show->getRawOriginal('gross_revenue') === null && $show->getRawOriginal('whatnot_net') === null) {
            $blockers[] = 'Show sales / settlement data is missing.';
        }

        if ($blockers !== []) {
            return $this->state('payroll_review', 'Payroll Review', 'Post-show approvals are complete, but payroll inputs still need attention.', 'warning', 5, array_values(array_unique($blockers)));
        }

        if ($payouts->isNotEmpty()) {
            return $this->state(
                'payroll',
                $batch ? 'In Pay Run' : 'Payroll Draft',
                $batch ? 'Show earnings are included in the current draft pay run.' : 'Payout calculations exist and are ready to be placed into a weekly pay run.',
                'primary',
                6,
                $blockers,
            );
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
