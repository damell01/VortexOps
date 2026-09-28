<?php

namespace App\Services;

use App\Models\FulfillmentPackage;
use App\Models\Show;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Two different signals feed this page, at two different confidence levels.
 *
 * "Confirmed" candidates (candidates()/delete()/deleteAllClear()): a show
 * only ever reaches status=cancelled with analytics_sync_status=unavailable
 * because WhatnotReportingReconciler saw Whatnot itself report an explicit
 * 0-minute duration at least two days after the scheduled date. That's
 * proof, not a guess, so these are safe to bulk-delete.
 *
 * "Flagged for review" (flaggedForReview()/deleteFlaggedReview()): the
 * softer sibling check in SyncWhatnotReporting flags any show 12+ hours past
 * its scheduled time with zero orders, shipments, units, gross, or net —
 * which usually does mean it never aired, but a real show can legitimately
 * have a dead night. In practice this is the signal that actually fires;
 * the confirmed-duration path needs the scraper to successfully read a
 * literal 0 off Whatnot's analytics page, which is far less common than a
 * dead show just returning no stable metrics at all. Flagged rows require a
 * human to look and confirm one at a time — never a bulk delete.
 *
 * Either way, deletion is refused if the show carries any real order,
 * payout, shipment, deduction, surcharge, streamer report, or fulfillment
 * package — several of those relationships cascade-delete at the database
 * level, and a no-show flag should never be the thing that silently erases
 * real financial or fulfillment history. Rows with none of that are safe:
 * the DB cascade only has to clean up the show's own audit trail (change
 * logs, reopening requests, streamer/fulfillment-user pivots), which has no
 * value once the show itself is gone.
 */
class NoShowCleanupService
{
    private const AUTO_EXCLUDED_NOTE_PREFIX = 'Automatically excluded:';

    /**
     * The softer, heuristic sibling check (reconcileEndedShowState() in
     * SyncWhatnotReporting): a show 12+ hours past its scheduled time with
     * zero orders, shipments, units, gross, or net. That's evidence a show
     * likely never aired, but — unlike an explicit 0-minute duration from
     * Whatnot itself — it's not proof, since a real show can legitimately
     * have zero sales on a bad night. These are surfaced for a human to
     * confirm one at a time, never bulk-deleted.
     */
    public const FLAGGED_FOR_REVIEW_NOTE = '[SYSTEM] Past show has no Whatnot sales, orders, shipments, gross, or net data. Verify whether the show happened or was cancelled.';

    private function withRelationCounts(Builder $query): Builder
    {
        return $query->with('channel')->withCount([
            'orders', 'payouts', 'shipments', 'deductionRequests', 'shippingSurcharges',
            'streamerLogEntry as streamer_log_entry_count',
        ]);
    }

    public function candidates(): Builder
    {
        return $this->withRelationCounts(
            Show::query()
                ->where('status', 'cancelled')
                ->where('analytics_sync_status', 'unavailable')
                ->where('analytics_sync_note', 'like', self::AUTO_EXCLUDED_NOTE_PREFIX.'%')
        )->orderByDesc('show_date');
    }

    /** Flagged by the softer heuristic and not already resolved one way or the other. */
    public function flaggedForReview(): Builder
    {
        return $this->withRelationCounts(
            Show::query()
                ->where('notes', 'like', '%'.self::FLAGGED_FOR_REVIEW_NOTE.'%')
                ->whereNotIn('status', ['cancelled', 'closed'])
        )->orderByDesc('show_date');
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

        $this->performDelete($show, 'confirmed no-show');
    }

    /**
     * The reviewer has looked at the show's own record and decided it never
     * aired, even though nothing here auto-confirmed that the way delete()
     * requires. Still refuses if any related record is attached, and still
     * requires the show to actually carry the review flag — this is not a
     * way to delete an arbitrary show.
     *
     * @throws \RuntimeException if the show isn't flagged, or is blocked
     */
    public function deleteFlaggedReview(Show $show): void
    {
        if (! str_contains((string) $show->notes, self::FLAGGED_FOR_REVIEW_NOTE)
            || in_array($show->status, ['cancelled', 'closed'], true)
        ) {
            throw new \RuntimeException('This show is not on the flagged-for-review list; refusing to delete it.');
        }

        $this->performDelete($show, 'reviewer-confirmed no-show');
    }

    /** @throws \RuntimeException if the show still has a related record attached */
    private function performDelete(Show $show, string $reason): void
    {
        $blocking = $this->blockingReferences($show);
        if ($blocking !== []) {
            $summary = collect($blocking)->map(fn (int $count, string $label) => "{$count} {$label}")->implode(', ');
            throw new \RuntimeException("Refusing to delete — this show still has: {$summary}.");
        }

        $description = "#{$show->id} \"{$show->title}\" ({$show->show_date?->toDateString()}, channel #{$show->whatnot_channel_id})";

        DB::transaction(function () use ($show) {
            $show->delete();
        });

        Log::info("No-show cleanup: permanently deleted {$reason} {$description}.");
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
