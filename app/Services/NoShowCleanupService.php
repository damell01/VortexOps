<?php

namespace App\Services;

use App\Models\FulfillmentPackage;
use App\Models\Show;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * A show only ever reaches status=cancelled with analytics_sync_status=
 * unavailable through one path: WhatnotReportingReconciler confirming Whatnot
 * reported an explicit 0-minute duration at least two days after the
 * scheduled date. That's the one place in the app that means "this genuinely
 * never aired" rather than "we haven't checked yet" or "the scraper had a
 * bad moment" — so it's the only thing this service treats as a delete
 * candidate.
 *
 * Deletion is still refused if the show carries any real order, payout,
 * shipment, deduction, surcharge, streamer report, or fulfillment package —
 * several of those relationships cascade-delete at the database level, and a
 * no-show flag should never be the thing that silently erases real financial
 * or fulfillment history. Rows with none of that are safe: the DB cascade
 * only has to clean up the show's own audit trail (change logs, reopening
 * requests, streamer/fulfillment-user pivots), which has no value once the
 * show itself is gone.
 */
class NoShowCleanupService
{
    private const AUTO_EXCLUDED_NOTE_PREFIX = 'Automatically excluded:';

    public function candidates(): Builder
    {
        return Show::query()
            ->where('status', 'cancelled')
            ->where('analytics_sync_status', 'unavailable')
            ->where('analytics_sync_note', 'like', self::AUTO_EXCLUDED_NOTE_PREFIX.'%')
            ->with('channel')
            ->withCount([
                'orders', 'payouts', 'shipments', 'deductionRequests', 'shippingSurcharges',
                'streamerLogEntry as streamer_log_entry_count',
            ])
            ->orderByDesc('show_date');
    }

    /** Relation name => count, for whatever is blocking deletion. Empty when safe. */
    public function blockingReferences(Show $show): array
    {
        $blocking = [
            'orders' => $show->orders_count ?? $show->orders()->count(),
            'payouts' => $show->payouts_count ?? $show->payouts()->count(),
            'shipments' => $show->shipments_count ?? $show->shipments()->count(),
            'deduction requests' => $show->deduction_requests_count ?? $show->deductionRequests()->count(),
            'shipping surcharges' => $show->shipping_surcharges_count ?? $show->shippingSurcharges()->count(),
            'streamer report' => $show->streamer_log_entry_count ?? ($show->streamerLogEntry ? 1 : 0),
            'fulfillment packages' => FulfillmentPackage::where('show_id', $show->id)->count(),
        ];

        return array_filter($blocking, fn (int $count) => $count > 0);
    }

    public function canDelete(Show $show): bool
    {
        return $this->blockingReferences($show) === [];
    }

    /** @throws \RuntimeException if the show is not a confirmed, unblocked no-show */
    public function delete(Show $show): void
    {
        if ($show->status !== 'cancelled'
            || $show->analytics_sync_status !== 'unavailable'
            || ! str_starts_with((string) $show->analytics_sync_note, self::AUTO_EXCLUDED_NOTE_PREFIX)
        ) {
            throw new \RuntimeException('This show was not auto-excluded as a confirmed no-show; refusing to delete it.');
        }

        $blocking = $this->blockingReferences($show);
        if ($blocking !== []) {
            $summary = collect($blocking)->map(fn (int $count, string $label) => "{$count} {$label}")->implode(', ');
            throw new \RuntimeException("Refusing to delete — this show still has: {$summary}.");
        }

        $description = "#{$show->id} \"{$show->title}\" ({$show->show_date?->toDateString()}, channel #{$show->whatnot_channel_id})";

        DB::transaction(function () use ($show) {
            $show->delete();
        });

        Log::info("No-show cleanup: permanently deleted confirmed no-show {$description}.");
    }

    /**
     * Deletes every unblocked candidate. Returns ['deleted' => int, 'skipped' => Show[]]
     * so the caller can tell the admin exactly which rows still need manual review.
     */
    public function deleteAllClear(): array
    {
        $deleted = 0;
        $skipped = [];

        foreach ($this->candidates()->get() as $show) {
            if ($this->canDelete($show)) {
                $this->delete($show);
                $deleted++;
            } else {
                $skipped[] = $show;
            }
        }

        return ['deleted' => $deleted, 'skipped' => $skipped];
    }
}
