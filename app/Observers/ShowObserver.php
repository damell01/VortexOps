<?php

namespace App\Observers;

use App\Jobs\NotifyShowPendingReview;
use App\Models\Show;
use App\Models\StreamerLogEntry;

class ShowObserver
{
    public function created(Show $show): void
    {
        $this->detectImportedShowStreamer($show, true);
    }

    public function updated(Show $show): void
    {
        if ($show->isDirty('status') && $show->status === 'pending_review') {
            NotifyShowPendingReview::dispatch($show->id);
        }

        // Whatnot upcoming shows can be renamed before they go live. Re-run when
        // the title changes. Also give older auto-imported shows one detection pass
        // if they pre-date automatic streamer detection.
        if ($show->wasChanged('title') || $show->ai_streamer_suggestion === null) {
            $this->detectImportedShowStreamer($show, $show->wasChanged('title'));
        }

        // When a show transitions to 'reconciled', auto-create a blank StreamerLogEntry
        // for the primary streamer if one does not already exist.
        if (
            $show->isDirty('status')
            && $show->status === 'reconciled'
        ) {
            $primaryStreamer = $show->primaryStreamer();

            if ($primaryStreamer && ! StreamerLogEntry::where('show_id', $show->id)->exists()) {
                StreamerLogEntry::create([
                    'show_id'       => $show->id,
                    'streamer_id'   => $primaryStreamer->id,
                    'status'        => 'pending',
                    'gross_revenue' => $show->gross_revenue,
                ]);
            }
        }
    }

    private function detectImportedShowStreamer(Show $show, bool $forceAttempt = false): void
    {
        if ($show->import_source !== 'auto_whatnot' || blank($show->title)) {
            return;
        }

        // Respect manual/admin assignments and prior confident automatic matches.
        if ($show->streamers()->exists()) {
            return;
        }

        // [] means detection already ran and found no title match. Retry only when
        // the title itself changed, since a renamed Upcoming show may now include
        // the streamer's name.
        if (! $forceAttempt && is_array($show->ai_streamer_suggestion)) {
            return;
        }

        $suggestions = $show->detectStreamers();

        // detectStreamers() only writes ai_streamer_suggestion when it has a
        // candidate. Persist an empty array quietly as a one-time-attempt marker so
        // unmatched historical shows are not re-scanned every ten minutes.
        if ($suggestions === []) {
            $show->updateQuietly(['ai_streamer_suggestion' => []]);
        }
    }
}
