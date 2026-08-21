<?php

namespace App\Observers;

use App\Jobs\NotifyShowPendingReview;
use App\Models\Show;
use App\Models\StreamerLogEntry;

class ShowObserver
{
    public function created(Show $show): void
    {
        $this->detectImportedShowStreamer($show);
    }

    public function updated(Show $show): void
    {
        if ($show->isDirty('status') && $show->status === 'pending_review') {
            NotifyShowPendingReview::dispatch($show->id);
        }

        // Upcoming Whatnot shows can be renamed before they go live. Re-run the
        // title matcher when an imported show's title changes, but never replace a
        // streamer that has already been deliberately attached.
        if ($show->wasChanged('title')) {
            $this->detectImportedShowStreamer($show);
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

    private function detectImportedShowStreamer(Show $show): void
    {
        if ($show->import_source !== 'auto_whatnot' || blank($show->title)) {
            return;
        }

        // Respect manual/admin assignments and any prior confident match.
        if ($show->streamers()->exists()) {
            return;
        }

        $show->detectStreamers();
    }
}
